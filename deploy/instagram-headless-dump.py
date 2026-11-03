#!/usr/bin/env python3
"""Dump recent Instagram posts via Playwright (works when Instaloader GraphQL is throttled).

Writes Archiver layout under data/instagram/{account}/{shortcode}/:
  post.json + 1.jpg, 2.jpg, ...
"""

from __future__ import annotations

import argparse
import json
import re
import sys
import time
from pathlib import Path
from urllib.parse import unquote, urlparse

import requests
from playwright.sync_api import TimeoutError as PlaywrightTimeout
from playwright.sync_api import sync_playwright


def normalize(value: str) -> str:
	value = value.strip()
	if "%" in value:
		value = unquote(value)
	return value.strip()


def load_cookies(root: Path) -> list[dict]:
	cookies_path = root / ".secret" / "instagram.cookies.json"
	session_path = root / ".secret" / "instagram.session"
	raw: dict[str, str] = {}

	if cookies_path.is_file():
		data = json.loads(cookies_path.read_text(encoding="utf-8"))
		if isinstance(data, dict):
			for key, value in data.items():
				if isinstance(value, str) and value.strip():
					raw[str(key)] = normalize(value)

	if session_path.is_file():
		sessionid = normalize(session_path.read_text(encoding="utf-8").splitlines()[0])
		if sessionid:
			raw.setdefault("sessionid", sessionid)

	if "sessionid" not in raw:
		raise SystemExit("Missing Instagram session (.secret/instagram.cookies.json or instagram.session).")

	user_id = raw["sessionid"].split(":", 1)[0]
	if user_id.isdigit():
		raw.setdefault("ds_user_id", user_id)

	out = []
	for name, value in raw.items():
		out.append(
			{
				"name": name,
				"value": value,
				"domain": ".instagram.com",
				"path": "/",
				"httpOnly": name in {"sessionid", "datr", "rur"},
				"secure": True,
				"sameSite": "None" if name == "rur" else "Lax",
			}
		)
	return out


def collect_shortcodes(page, account: str, limit: int) -> list[str]:
	page.goto(f"https://www.instagram.com/{account}/", wait_until="domcontentloaded", timeout=90_000)
	time.sleep(4)
	for sel in (
		'button:has-text("Allow")',
		'button:has-text("Accept")',
		'button:has-text("Not Now")',
		'button:has-text("Teď ne")',
	):
		try:
			page.locator(sel).first.click(timeout=800)
		except Exception:
			pass

	# Scroll a bit to load the grid.
	for _ in range(3):
		page.mouse.wheel(0, 2400)
		time.sleep(1.2)

	hrefs = page.eval_on_selector_all(
		'a[href*="/p/"], a[href*="/reel/"]',
		"els => els.map(e => e.getAttribute('href') || '')",
	)
	prefix = f"/{account}/"
	codes: list[str] = []
	seen: set[str] = set()
	for href in hrefs:
		if not href or prefix not in href:
			continue
		match = re.search(r"/(?:p|reel)/([A-Za-z0-9_-]+)/", href)
		if not match:
			continue
		code = match.group(1)
		if code in seen:
			continue
		seen.add(code)
		codes.append(code)
		if len(codes) >= limit:
			break
	return codes


def download_binary(url: str, dest: Path, session: requests.Session) -> None:
	resp = session.get(url, timeout=60)
	resp.raise_for_status()
	dest.write_bytes(resp.content)


def is_post_media_url(url: str) -> bool:
	if "fbcdn.net" not in url and "cdninstagram.com" not in url:
		return False
	if "static.cdninstagram.com" in url:
		return False
	# Avatars / tiny crops.
	if "t51.2885-19" in url:
		return False
	if re.search(r"s\d+x\d+", url):
		return False
	# Carousel / feed post images.
	return "t51.82787" in url or "t51.71878" in url or "/t51." in url


def media_dedupe_key(url: str) -> str:
	name = Path(urlparse(url).path).name
	# 876258214_18399528337162938_5825…_n.jpg → keep id stem
	return name.split("?")[0]


def dump_post(page, session: requests.Session, account: str, shortcode: str, out_dir: Path) -> bool:
	post_dir = out_dir / account / shortcode
	if (post_dir / "post.json").is_file() and any(post_dir.glob("*.jpg")):
		print(f"  skip existing @{account}/{shortcode}")
		return False

	caption = ""
	taken_at = int(time.time())
	captured: dict[str, tuple[str, int]] = {}

	def on_response(resp) -> None:
		url = resp.url
		if resp.status != 200 or not is_post_media_url(url):
			return
		ctype = (resp.headers.get("content-type") or "").lower()
		if "image" not in ctype:
			return
		try:
			size = int(resp.headers.get("content-length") or "0")
		except ValueError:
			size = 0
		if size and size < 80_000:
			return
		key = media_dedupe_key(url)
		prev = captured.get(key)
		if prev is None or size >= prev[1]:
			captured[key] = (url, size)

	page.on("response", on_response)
	try:
		page.goto(
			f"https://www.instagram.com/{account}/p/{shortcode}/",
			wait_until="domcontentloaded",
			timeout=90_000,
		)
		time.sleep(2)
		# Advance carousel so all slide images load.
		for _ in range(15):
			try:
				btn = page.get_by_role("button", name=re.compile(r"Next|Další", re.I))
				if btn.count() == 0:
					break
				btn.first.click(timeout=1000)
				time.sleep(0.7)
			except Exception:
				break
		time.sleep(1)
	finally:
		try:
			page.remove_listener("response", on_response)
		except Exception:
			pass

	try:
		og = page.locator('meta[property="og:description"]').first.get_attribute("content", timeout=2000) or ""
		quote = re.search(r':\s*"(.*)"\s*\.?\s*$', og, re.S)
		if quote:
			caption = quote.group(1).replace("\\n", "\n")
		elif ":" in og:
			caption = og.split(":", 1)[1].strip().strip('"').strip()
		else:
			caption = og
	except Exception:
		pass
	try:
		from datetime import datetime

		dt = page.locator("time").first.get_attribute("datetime", timeout=2000)
		if dt:
			taken_at = int(datetime.fromisoformat(dt.replace("Z", "+00:00")).timestamp())
	except Exception:
		pass

	# Prefer caption text from the visible post body when og is noisy.
	if not caption or caption.startswith("http") or len(caption) < 8:
		try:
			body = page.inner_text("article", timeout=2000)
			# First non-empty long-ish line after username chrome.
			for line in body.splitlines():
				line = line.strip()
				if len(line) > 40 and "Following" not in line and "Sleduji" not in line:
					caption = line
					break
		except Exception:
			pass

	ranked = sorted(captured.values(), key=lambda item: item[1], reverse=True)
	# Keep reasonable carousel size; largest first then restore discovery order by size desc is fine.
	unique_urls = [url for url, size in ranked if size >= 80_000][:12]
	if not unique_urls:
		print(f"  no media for @{account}/{shortcode}")
		return False

	post_dir.mkdir(parents=True, exist_ok=True)
	for old in post_dir.glob("*"):
		old.unlink(missing_ok=True)

	media_meta = []
	for index, media_url in enumerate(unique_urls, start=1):
		name = f"{index}.jpg"
		dest = post_dir / name
		try:
			download_binary(media_url, dest, session)
			if dest.stat().st_size < 80_000:
				dest.unlink(missing_ok=True)
				continue
			media_meta.append({"local_image": name})
		except Exception as exc:  # noqa: BLE001
			print(f"  media fail {name}: {exc}")

	final_meta = []
	for new_index, item in enumerate(media_meta, start=1):
		src = post_dir / item["local_image"]
		dest_name = f"{new_index}.jpg"
		dest = post_dir / dest_name
		if src != dest and src.exists():
			src.rename(dest)
		if dest.exists():
			final_meta.append({"local_image": dest_name})

	if not final_meta:
		print(f"  no saved media for @{account}/{shortcode}")
		return False

	post = {
		"account": account,
		"shortcode": shortcode,
		"taken_at": taken_at,
		"caption": caption,
		"media": final_meta,
	}
	(post_dir / "post.json").write_text(
		json.dumps(post, ensure_ascii=False, indent=2) + "\n",
		encoding="utf-8",
	)
	print(f"  dumped @{account}/{shortcode} ({len(final_meta)} images)")
	return True


def main() -> int:
	parser = argparse.ArgumentParser(description=__doc__)
	parser.add_argument("--root", type=Path, required=True)
	parser.add_argument("--count", type=int, default=12)
	parser.add_argument(
		"--shortcode",
		action="append",
		default=[],
		help="Dump specific shortcode(s) for the first profile (repeatable)",
	)
	parser.add_argument("profiles", nargs="+")
	args = parser.parse_args()

	root = args.root.resolve()
	out_dir = root / "data" / "instagram"
	out_dir.mkdir(parents=True, exist_ok=True)
	cookies = load_cookies(root)

	session = requests.Session()
	session.headers.update(
		{
			"User-Agent": (
				"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
				"AppleWebKit/537.36 (KHTML, like Gecko) "
				"Chrome/131.0.0.0 Safari/537.36"
			),
			"Referer": "https://www.instagram.com/",
		}
	)
	for cookie in cookies:
		session.cookies.set(cookie["name"], cookie["value"], domain=".instagram.com", path="/")

	imported = 0
	with sync_playwright() as playwright:
		browser = playwright.chromium.launch(headless=True)
		context = browser.new_context(
			user_agent=session.headers["User-Agent"],
			viewport={"width": 1280, "height": 900},
			locale="cs-CZ",
		)
		context.add_cookies(cookies)
		page = context.new_page()

		for account_index, account in enumerate(args.profiles):
			print(f"Scanning @{account}...")
			if account_index == 0 and args.shortcode:
				codes = list(dict.fromkeys(args.shortcode))
				print(f"  using explicit shortcode(s): {', '.join(codes)}")
			else:
				try:
					codes = collect_shortcodes(page, account, args.count)
				except PlaywrightTimeout as exc:
					print(f"  profile timeout: {exc}", file=sys.stderr)
					continue
				print(f"  found {len(codes)} shortcode(s)")
			for code in codes:
				try:
					if dump_post(page, session, account, code, out_dir):
						imported += 1
				except Exception as exc:  # noqa: BLE001
					print(f"  fail {code}: {exc}", file=sys.stderr)
				time.sleep(1.5)

		browser.close()

	print(f"Headless dump wrote {imported} new post(s).")
	return 0


if __name__ == "__main__":
	raise SystemExit(main())
