#!/usr/bin/env python3
"""Incremental Instagram dump via Instaloader using .secret session cookies."""

from __future__ import annotations

import argparse
import json
import sys
from pathlib import Path
from urllib.parse import unquote

import instaloader
from instaloader import Profile


def normalize(value: str) -> str:
	value = value.strip()
	if "%3A" in value or "%3a" in value or "%2C" in value or "%2c" in value:
		value = unquote(value)
	return value.strip()


def load_cookies(root: Path) -> dict[str, str]:
	cookies_path = root / ".secret" / "instagram.cookies.json"
	session_path = root / ".secret" / "instagram.session"
	cookies: dict[str, str] = {}

	if cookies_path.is_file():
		raw = json.loads(cookies_path.read_text(encoding="utf-8"))
		if not isinstance(raw, dict):
			raise SystemExit(f"Invalid cookies JSON: {cookies_path}")
		for key, value in raw.items():
			if isinstance(value, str) and value.strip():
				cookies[str(key)] = normalize(value)

	if session_path.is_file():
		sessionid = normalize(session_path.read_text(encoding="utf-8").splitlines()[0])
		if sessionid:
			cookies.setdefault("sessionid", sessionid)

	if "sessionid" not in cookies:
		raise SystemExit(
			"Missing Instagram session. Put sessionid in .secret/instagram.session "
			"and/or full cookies in .secret/instagram.cookies.json."
		)

	sessionid = cookies["sessionid"]
	user_id = sessionid.split(":", 1)[0]
	if user_id.isdigit():
		cookies.setdefault("ds_user_id", user_id)

	return cookies


def apply_cookies(loader: instaloader.Instaloader, cookies: dict[str, str]) -> None:
	session = loader.context._session
	for name, value in cookies.items():
		session.cookies.set(name, value, domain=".instagram.com", path="/")
	# Browser-like headers help avoid immediate login redirects.
	session.headers.update(
		{
			"User-Agent": (
				"Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) "
				"AppleWebKit/537.36 (KHTML, like Gecko) "
				"Chrome/120.0.0.0 Safari/537.36"
			),
			"X-CSRFToken": cookies.get("csrftoken", ""),
			"X-IG-App-ID": "936619743392459",
			"X-Requested-With": "XMLHttpRequest",
			"Referer": "https://www.instagram.com/",
		}
	)


def main() -> int:
	parser = argparse.ArgumentParser(description=__doc__)
	parser.add_argument("--root", type=Path, required=True, help="App root (contains .secret + data/instagram)")
	parser.add_argument("--count", type=int, default=20, help="Max newest posts per profile")
	parser.add_argument("profiles", nargs="+", help="Instagram usernames")
	args = parser.parse_args()

	root = args.root.resolve()
	cookies = load_cookies(root)

	loader = instaloader.Instaloader(
		dirname_pattern=str(root / "data" / "instagram" / "{profile}"),
		filename_pattern="{date_utc}_UTC",
		download_videos=False,
		download_video_thumbnails=False,
		download_geotags=False,
		download_comments=False,
		save_metadata=True,
		compress_json=True,
		post_metadata_txt_pattern="",
		max_connection_attempts=3,
	)
	apply_cookies(loader, cookies)

	# Prefer load_session when we have the usual browser cookie set.
	try:
		loader.load_session(cookies.get("ds_user_id", "instagram"), cookies)
	except Exception as exc:  # noqa: BLE001 — fall back to cookie jar only
		print(f"load_session fallback: {exc}", file=sys.stderr)

	username = loader.test_login()
	if not username:
		# test_login can false-negative on graphql; try a profile probe instead.
		try:
			probe = Profile.from_username(loader.context, args.profiles[0])
			print(f"Session probe ok (profile @{probe.username}, full_name={probe.full_name!r})")
			username = cookies.get("ds_user_id", "session")
		except Exception as exc:  # noqa: BLE001
			print(f"Instagram session rejected: {exc}", file=sys.stderr)
			return 1
	else:
		print(f"Instaloader session ok (@{username})")
	loader.context.username = username

	for name in args.profiles:
		print(f"Downloading @{name} (fast-update, count={args.count})...")
		profile = Profile.from_username(loader.context, name)
		loader.posts_download_loop(
			profile.get_posts(),
			target=name,
			fast_update=True,
			max_count=args.count,
			owner_profile=profile,
		)

	return 0


if __name__ == "__main__":
	raise SystemExit(main())
