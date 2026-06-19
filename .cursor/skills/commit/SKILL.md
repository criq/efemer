---
name: commit
description: Create git commits split into logical chunks and push after each commit. Use when the user asks to commit, create a commit, save work to git, or stage and commit changes.
---

# Commit

Read this skill when the user asks to commit. Follow the git-commits rule: logical chunks, push after each commit.

## Before committing

1. Confirm the user explicitly asked to commit. If unclear, ask first.
2. Inspect the full change set: `git status`, `git diff`, `git log -5 --oneline`
3. Identify **logical chunks** — separate commits for unrelated work.

### Chunking examples

| Changes | Commits |
|---------|---------|
| New API endpoint + unrelated CSS fix | 2 commits |
| Database migration + models using new columns | 2 commits (migration first) |
| Rename class + update all references | 1 commit |
| Feature work + `composer.lock` from new dependency | 1 commit if dependency is required for the feature |

## Git safety

- NEVER update git config
- NEVER run destructive git commands (push --force, hard reset, etc.) unless explicitly requested
- NEVER skip hooks (`--no-verify`, `--no-gpg-sign`, etc.) unless explicitly requested
- NEVER force push to main/master — warn the user if they request it
- Avoid `git commit --amend` unless: user requested amend, HEAD commit was created by you in this conversation and not pushed, or pre-commit hook auto-modified files after a successful commit
- If commit FAILED or was REJECTED by hook, fix and create a NEW commit — never amend
- Do not commit secrets (.env, credentials.json, etc.) — warn the user if they request it

## Workflow per chunk

For each logical chunk, run sequentially:

1. **Stage** only files for this chunk: `git add <paths>`
2. **Draft message** — 1–2 sentences focused on *why*, matching recent `git log` style
3. **Commit** via HEREDOC:

```bash
git commit -m "$(cat <<'EOF'
Your message here.

EOF
)"
```

4. **Verify**: `git status`
5. **Push** (required after every successful commit):

```bash
git push
```

If no upstream branch:

```bash
git push -u origin HEAD
```

6. Repeat for remaining chunks until the working tree is clean (or only intentionally uncommitted files remain).

## Parallel inspection only

Run `git status`, `git diff`, and `git log` in parallel at the start — not between staging, committing, and pushing for a single chunk.

## Do not

- Combine unrelated changes into one commit to save time
- Push before commit succeeds
- Skip push unless the user explicitly says not to push
- Use interactive git flags (`-i`) — not supported
