# Hostbase SoftLayer Importer

Imports into or updates Hostbase with hardware, virtual guests, and subnets (with all IPs) as retrieved via the SoftLayer API.

## Installation

1. Download/clone this whole repository or install with `composer create-project shift31/hostbase-importer-softlayer`
2. Run `composer install` from the project root

## Configuration

In the project root, create a config.ini:

```
slApiUsername = your_softlayer_api_username
slApiKey = your_softlayer_api_key
hostbaseUrl = "http://your.hostbase.server"
```

## Run

1. `chmod +x bin/hostbase-importer-sl`
2. `bin/hostbase-importer-sl`