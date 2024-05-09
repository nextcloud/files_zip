<!--
 - SPDX-FileCopyrightText: 2021 Nextcloud GmbH and Nextcloud contributors
 - SPDX-License-Identifier: AGPL-3.0-or-later
-->
# Zipper

[![REUSE status](https://api.reuse.software/badge/github.com/nextcloud/files_zip)](https://api.reuse.software/info/github.com/nextcloud/files_zip)

Create zip archives from one or multiple files from within Nextcloud. The archive will be created in the background during cron job execution, so make sure that you have setup a regular cron job. Once the file has been created, the user will be notified about that.

## Usage

After installing and enabling [the Zipper app](https://apps.nextcloud.com/apps/files_zip), a new contextual menu option labeled `Compress to Zip` will appear when right-clicking a file or folder as well as under the `...Actions` menu at the top of the file list in the Nextcloud Web inteface. A notification will be generated immediately for the user to inform them that the zip file creation is pending (queued). An additional notification will be generated when the zip file is available for download.

## Development

The app requires frontend code build in order to run it from the git repostitory:
- Install dependencies: `npm ci`
- Build the app `npm run build`

## API

### Capabilities Endpoint

The Capabilities endpoint will announce the possibility to create zip files through the API with the available API version. Currently only `v1` is available.

```json
  "files_zip": {
    "apiVersion": "v1"
  },
```

### Scheduling a zip file creation

POST /ocs/v2.php/apps/files_zip/api/v1/zip

Parameters:
- fileIds: *(int[])* List of file ids to add to the archive, e.g. `[18633,18646,18667]`
- target: *(string)* Full path of the target zip file in the user directory, e.g. `/path/to/file.zip`

## Configuration

### Limiting File Size

In some cases it might be wanted to limit the maximum size of files in total that may be added to zip files. That might be for example useful if the amount of space on /tmp needs to be calculated in order to have enough space for compression.

Setting the limit to 1GB (in bytes):
```
occ config:app:set files_zip max_compress_size --value=1073741824
```

The default value is unlimited (-1).
