# Archive security design

Compression and extraction treat archive-related input as untrusted. The design deliberately prefers rejecting unusual archives or file names over weakening the security boundary.

## Security invariants

1. **Never extract directly to the Nextcloud data directory.**
   Archive entries are written only through Nextcloud's public `OCP\Files` APIs.
2. **Never call `ZipArchive::extractTo()` or `PharData::extractTo()`.**
   Every entry is inspected first and copied individually afterwards.
3. **Inspect and extract the same immutable temporary copy.**
   This prevents a source archive from changing between validation and extraction.
4. **All archive paths pass one central validator.**
   Absolute paths, Windows drive paths, parent traversal, NUL/control bytes, invalid UTF-8 and excessive nesting are rejected.
5. **Links and special filesystem objects are rejected.**
   Symlinks, hardlinks, devices, FIFOs, sockets, sparse-file extensions and link metadata are not extracted.
6. **No silent overwrite.**
   Extraction always creates a new destination directory and compression refuses an existing archive target.
7. **Duplicate normalized paths are rejected.**
   Two archive entries may not resolve to the same destination path.
8. **Resource limits are enforced before and while writing.**
   Entry count, uncompressed size, suspicious compression ratio, temporary archive size, metadata size, destination quota and temporary disk space are bounded. Streaming code rechecks byte counts where practical.
9. **Share permissions remain authoritative.**
   Incoming shares that disable downloading cannot be used as extraction or compression sources.
10. **Background jobs revalidate everything.**
    Checks performed when scheduling are only for user experience; permissions, targets, formats and archive contents are checked again when the job executes.
11. **External binaries have fixed absolute paths.**
    The application never searches `$PATH` and does not accept executable paths, switches or shell fragments from user or administrator input. The 7-Zip backend uses only `/usr/bin/7z` and requires version 25.01 or newer.
12. **External commands never pass through a shell.**
    7-Zip operations use `proc_open()` with an argument array. Operational calls terminate switch and `@listfile` parsing with `--` and disable wildcard processing with `-spd` where applicable.
13. **7-Zip never extracts directly to a destination directory.**
    A 7z archive is first inspected with the technical listing. Every entry path, type and size is validated. Approved files are then requested individually through `7z x -so`; output is capped at the previously inspected size, written to a neutral temporary file, and finally copied to the validated destination through `OCP\Files`.
14. **7-Zip never receives Nextcloud storage paths for compression.**
    TAR, TAR.GZ and 7z creation first stages selected nodes through `OCP\Files` in a private `ITempManager` directory. The external binary only sees that controlled staging tree. The finished archive is copied back through `OCP\Files`.
15. **Temporary storage must retain a safety reserve.**
    Compression performs conservative free-space checks before staging and checks free space while streaming. TAR.GZ accounts for the intermediate TAR. Extraction limits the temporary immutable archive and individual 7z entry files.
16. **Encrypted archives are fail-closed in the initial implementation.**
    Password-protected/encrypted entries are rejected instead of prompting for or retaining secrets in a background job.

## Threats covered

- Zip Slip / path traversal (`../`, absolute paths, Windows drive paths)
- Symlink and hardlink escape attacks
- Device/FIFO/socket creation
- Archive bombs and excessive entry counts
- Huge TAR metadata records
- Duplicate-path overwrite tricks
- Quota exhaustion and temporary-disk exhaustion
- TOCTOU changes between inspection and extraction
- Bypass of disabled-download share permissions
- Partial extraction after an error (the newly created extraction root is removed on failure)
- Partial archive copies after an error (the new archive target is removed on failure)
- Shell-command injection through archive names, file names or user-controlled executable paths
- Option / `@listfile` injection into 7-Zip command lines
- External-binary path traversal or link materialization into Nextcloud storage
- 7-Zip output expanding beyond the size accepted during inspection

## Intentionally unsupported

The first secure implementation rejects features that are difficult to reproduce safely and consistently across storage backends, including encrypted/password-protected archives, archive links, special files, sparse TAR files and ambiguous/malformed technical listings. Compatibility can be expanded later only when the same security guarantees can be preserved.
