# Uploads Directory

This directory stores user-uploaded photos for lost and found items.

## File Naming
- Files are named with unique IDs: `item_[unique_id].[extension]`
- Prevents filename conflicts and overwrites

## Security
- Only image files are accepted (JPG, PNG, GIF, WEBP)
- Maximum file size: 5MB
- File extensions are validated server-side
- Directory permissions: 0755

## Storage
- Photos are stored locally in this directory
- File paths are saved in the database as `uploads/filename.ext`
- For production, consider using cloud storage (AWS S3, Cloudinary, etc.)

## Backup
Remember to backup this directory along with your database backups.
