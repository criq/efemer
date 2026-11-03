#!/usr/bin/env python3
"""Incremental Instagram dump via Instaloader using .secret/instagram.session (sessionid)."""

from __future__ import annotations

import argparse
import sys
from pathlib import Path
from urllib.parse import unquote

import instaloader
from instaloader import Profile


def load_sessionid(root: Path) -> str:
	path = root / ".secret" / "instagram.session"
	if not path.is_file():
		raise SystemExit(f"Missing Instagram session file: {path}")
	raw = path.read_text(encoding="utf-8").strip().splitlines()[0].strip()
	if "%3A" in raw or "%3a" in raw:
		raw = unquote(raw)
	if not raw:
		raise SystemExit(f"Empty Instagram session file: {path}")
	return raw


def main() -> int:
	parser = argparse.ArgumentParser(description=__doc__)
	parser.add_argument("--root", type=Path, required=True, help="App root (contains .secret + data/instagram)")
	parser.add_argument("--count", type=int, default=20, help="Max newest posts per profile")
	parser.add_argument("profiles", nargs="+", help="Instagram usernames")
	args = parser.parse_args()

	root = args.root.resolve()
	sessionid = load_sessionid(root)
	user_id = sessionid.split(":", 1)[0]

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
	loader.context._session.cookies.set("sessionid", sessionid, domain=".instagram.com")
	if user_id.isdigit():
		loader.context._session.cookies.set("ds_user_id", user_id, domain=".instagram.com")

	username = loader.test_login()
	if not username:
		print("Instagram sessionid rejected (login required / expired).", file=sys.stderr)
		return 1
	loader.context.username = username
	print(f"Instaloader session ok (@{username})")

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
