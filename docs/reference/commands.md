# Artisan commands

| Command | Description |
|---|---|
| [`imagepresets:clear`](#imagepresetsclear) | Delete generated images |
| [`imagepresets:verify`](#imagepresetsverify) | Find corrupted, orphaned and suspect cached files |

Usage and caveats — [Cache maintenance](../usage/cache-maintenance.md).

## imagepresets:clear

```bash
php artisan imagepresets:clear [--disk=] [--path=] [--temp]
```

| Option | Default | Description |
|---|---|---|
| `--disk=` | `disk` config | Disk to clear |
| `--path=` | `path` config | Directory inside the disk to delete |
| `--temp` | — | Also empty `source_dir` and `temp_dir` |

> [!WARNING]
> An empty `path` deletes everything on the disk.

Always exits with `0`.

## imagepresets:verify

```bash
php artisan imagepresets:verify [--disk=] [--path=] [--delete] [--deep] [--gray-threshold=25] [--delete-suspects]
```

| Option | Default | Description |
|---|---|---|
| `--disk=` | `disk` config | Disk to scan. Local disks only |
| `--path=` | `path` config | Directory to scan, recursively |
| `--delete` | off | Delete corrupted files and `*.tmp*` files older than one hour. Without it — dry run |
| `--deep` | off | Also decode each `.webp` and flag gray-filler suspects |
| `--gray-threshold=` | `25` | Percent of sampled pixels (0–100) that must be `rgb(128,128,128)` to flag a suspect |
| `--delete-suspects` | off | Delete the suspects; implies `--deep` |

Files checked: `jpg`, `jpeg`, `png`, `gif`, `webp`, `avif`. Output lines are `Corrupted: …`, `Orphaned tmp: …`, `Suspect webp: … — <reason>` (with `(deleted)` when removed), followed by a summary. Always exits with `0`, also when problems are found.
