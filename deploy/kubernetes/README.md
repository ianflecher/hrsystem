# Docker Desktop Kubernetes

This folder runs a standalone Central Admin service beside HRIS, Imprint
Production, and Imprint Marketing for local testing. The scanner is
intentionally excluded.

## First setup

Clone the four repositories beside one another:

```text
D:\GitHub\hris
D:\GitHub\production
D:\GitHub\marketing
D:\GitHub\imprint-admin
```

Install Docker Desktop, enable Kubernetes in Docker Desktop, and make sure
Docker Desktop is running. Docker Desktop includes the `docker` and `kubectl`
commands used by the setup script.

From the HRIS repository, run PowerShell:

```powershell
.\deploy\kubernetes\start-local.ps1
```

The script builds all four images locally, creates local-only Kubernetes
secrets when they do not already exist, applies the namespace and services, and
starts hidden port-forward supervisors that reconnect when a Kubernetes pod or
connection is replaced. It does not overwrite existing database, app, or
Central Admin secrets.

The application databases remain in Docker Desktop Kubernetes volumes; they are
not committed to GitHub. This keeps employee, customer, payroll, order, and
marketing records out of source control. The local test cluster can still be
recreated with demo records when needed:

```powershell
.\deploy\kubernetes\start-local.ps1 -SeedDemoData
```

Use that option only for a disposable test cluster. It resets the Production
business tables before loading demo orders and runs the Marketing demo seeder.
For real records, restore a database backup into the named Kubernetes volumes
instead of committing the database file.

If the other repositories are in different folders, pass their paths:

```powershell
.\deploy\kubernetes\start-local.ps1 `
  -ProductionPath 'D:\Projects\production' `
  -MarketingPath 'D:\Projects\marketing' `
  -CentralAdminPath 'D:\Projects\imprint-admin'
```

## Local URLs

- Central Admin: `http://127.0.0.1:30083`
- HRIS: `http://127.0.0.1:30080`
- Production: `http://127.0.0.1:30081`
- Marketing: `http://127.0.0.1:30082`

Sign in to Central Admin with `admin` / `imprint123` on the first local run.
Change that secret before using it outside local testing. Central Admin checks
all three services and provides links to each one. It is a separate login, not
shared single sign-on; each application keeps its own login session.

The Kubernetes data is stored in Docker Desktop volumes. Keep Docker Desktop
running while using the applications. This setup is for testing on the same
computer; public access through Cloudflare Tunnel is a separate deployment
step.
