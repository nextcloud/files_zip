# Zipper

Create zip archives from one or multiple files from within Nextcloud. The archive will be created in the background during cron job execution. Once the file has been created, the user will be notified about that.

### Development

The app requires frontend code build in order to run it from the git repostitory:
- Install dependencies: `npm ci`
- Build the app `npm run build`

### API documentation

The Capabilities endpoint will announce the possibility to create zip files through the API with the available API version. Currently only `v1` is available.

```json
  "files_zip": {
    "apiVersion": "v1"
  },
```

#### Schedule a zip file creation

POST /ocs/v2.php/apps/files_zip/api/v1/zip

Parameters:
- fileIds: *(int[])* List of file ids to add to the archive, e.g. `[18633,18646,18667]`
- target: *(string)* Full path of the target zip file in the user directory, e.g. `/path/to/file.zip`

