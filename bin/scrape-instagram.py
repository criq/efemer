#!/usr/bin/env python3

from __future__ import annotations

import json
import os
import sys
from pathlib import Path
from urllib.parse import unquote

ROOT = Path(__file__).resolve().parents[1]
sys.path.insert(0, str(ROOT / "tmp" / "instagram-venv" / "lib"))

try:
	import instaloader
except ImportError:
	sys.path.insert(0, str(next((ROOT / "tmp" / "instagram-venv" / "lib").glob("python*/site-packages"), ROOT)))
	import instaloader

SESSION_FILE = ROOT / ".secret" / "instagram.session"
OUT_DIR = ROOT / "data" / "instagram"
ACCOUNTS = ("efemer_flowers", "himmlhaus")


def load_sessionid() -> str:
	env = os.environ.get("INSTAGRAM_SESSIONID", "").strip()
	if env:
		raw = env
	elif SESSION_FILE.exists():
		raw = SESSION_FILE.read_text().strip().splitlines()[0].strip()
	else:
		raise RuntimeError("Missing INSTAGRAM_SESSIONID or .secret/instagram.session")
	if "%3A" in raw or "%3a" in raw:
		raw = unquote(raw)
	return raw.strip()


def loader() -> instaloader.Instaloader:
	L = instaloader.Instaloader(
		download_comments=False,
		download_geotags=False,
		download_video_thumbnails=False,
		save_metadata=False,
		compress_json=False,
		post_metadata_txt_pattern="",
		filename_pattern="{mediaid}",
		quiet=False,
	)
	sessionid = load_sessionid()
	L.context._session.cookies.set("sessionid", sessionid, domain=".instagram.com")
	logged = L.test_login()
	if not logged:
		raise RuntimeError("Instagram session is not logged in.")
	print(f"Logged in as {logged}", flush=True)
	return L


def local_media(post_dir: Path) -> list[dict]:
	items = []
	files = sorted([p for p in post_dir.iterdir() if p.suffix.lower() in {".jpg", ".jpeg", ".png", ".webp", ".mp4"}])
	for path in files:
		item = {"pk": path.stem, "width": None, "height": None}
		if path.suffix.lower() == ".mp4":
			item["local_video"] = path.name
		else:
			item["local_image"] = path.name
		items.append(item)
	return items


def scrape_account(L: instaloader.Instaloader, account: str, max_new: int | None) -> int:
	profile = instaloader.Profile.from_username(L.context, account)
	account_dir = OUT_DIR / account
	account_dir.mkdir(parents=True, exist_ok=True)
	count = 0
	downloaded = 0
	for post in profile.get_posts():
		post_dir = account_dir / post.shortcode
		post_dir.mkdir(parents=True, exist_ok=True)
		json_path = post_dir / "post.json"
		if json_path.exists():
			count += 1
			continue
		if max_new is not None and downloaded >= max_new:
			print(f"[{account}] reached --max-new={max_new}", flush=True)
			break
		L.dirname_pattern = str(post_dir)
		try:
			L.download_post(post, target=post.shortcode)
		except Exception as error:
			print(f"[{account}] skip {post.shortcode}: {error}", flush=True)
			continue
		payload = {
			"account": account,
			"shortcode": post.shortcode,
			"pk": str(post.mediaid),
			"taken_at": int(post.date_utc.timestamp()),
			"media_type": post.typename,
			"product_type": None,
			"caption": post.caption or "",
			"location": post.location.name if post.location else None,
			"media": local_media(post_dir),
		}
		json_path.write_text(json.dumps(payload, ensure_ascii=False, indent=2) + "\n")
		count += 1
		downloaded += 1
		if count % 10 == 0:
			print(f"[{account}] {count} posts", flush=True)
	(account_dir / "index.json").write_text(
		json.dumps({"account": account, "count": count}, ensure_ascii=False, indent=2) + "\n"
	)
	print(f"[{account}] done ({count}, {downloaded} new)", flush=True)
	return count


def main() -> int:
	max_new = None
	accounts = list(ACCOUNTS)
	args = sys.argv[1:]
	i = 0
	while i < len(args):
		arg = args[i]
		if arg.startswith("--max-new="):
			max_new = int(arg.split("=", 1)[1])
		elif arg == "--max-new" and i + 1 < len(args):
			max_new = int(args[i + 1])
			i += 1
		elif arg.startswith("--account="):
			accounts = [arg.split("=", 1)[1]]
		else:
			print(f"Unknown argument: {arg}", file=sys.stderr)
			return 2
		i += 1

	OUT_DIR.mkdir(parents=True, exist_ok=True)
	L = loader()
	total = 0
	for account in accounts:
		total += scrape_account(L, account, max_new)
	print(f"Done. {total} posts.", flush=True)
	return 0


if __name__ == "__main__":
	sys.exit(main())
