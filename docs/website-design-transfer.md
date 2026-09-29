# Transfer a Website Studio design

1. On your local EcclesiaOS installation, open **Website Studio** and save your changes.
2. Under **Export or import a website design**, click **Export website ZIP**.
3. Deploy an EcclesiaOS version containing this feature (the same or a newer compatible version) to the live server. Enable PHP ZIP and make public storage writable and publicly accessible.
4. Sign in as an administrator of the destination church and open **Website Studio**. Export its current design first if you want a backup.
5. Select the exported ZIP, check the replacement confirmation, and click **Import website design**.
6. Review the public site, branding, contact details, external links, and publication status.

The package includes website settings, navigation, pages and page order, reusable sections and widget content/settings, plus referenced Website Studio uploads and configured branding files. Media is copied into the destination church's storage, and links to the source church's public website are rewritten for the destination. Imported pages retain their draft/published status. Existing pages absent from the package are soft-deleted; existing uploaded files are retained.

Events, sermons, ministries, campuses, store products, and other church records are not transferred. Dynamic widgets display the destination church's records. Remote media remains linked, so it still requires its original host. This is a design package for EcclesiaOS, not a standalone website or a full database backup.

Limits: 100 pages, 2,000 media files, 200 MB of media and a 5 MB manifest. Supported bundled media: JPG, PNG, GIF, WebP, ICO, MP4, WebM, and Ogg. PHP `upload_max_filesize`, `post_max_size`, and the web server request limit must accommodate the ZIP; the application upload limit is 200 MB. Failed imports roll back database changes and remove newly staged media.
