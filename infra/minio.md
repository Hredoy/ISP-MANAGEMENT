# MinIO Setup

Create buckets:

```bash
mc alias set isp-os http://127.0.0.1:9000 "$AWS_ACCESS_KEY_ID" "$AWS_SECRET_ACCESS_KEY"
mc mb isp-os/isp-os-uploads
mc mb isp-os/isp-os-exports
mc mb isp-os/isp-os-backups
```

Tenant object layout:

```text
uploads/{tenant_id}/logos/
uploads/{tenant_id}/invoices/
uploads/{tenant_id}/reports/
uploads/{tenant_id}/backups/
uploads/{tenant_id}/technician-photos/
```

Laravel disk:

```env
FILESYSTEM_DISK=s3
AWS_ACCESS_KEY_ID=
AWS_SECRET_ACCESS_KEY=
AWS_DEFAULT_REGION=us-east-1
AWS_BUCKET=isp-os-uploads
AWS_ENDPOINT=http://127.0.0.1:9000
AWS_USE_PATH_STYLE_ENDPOINT=true
```
