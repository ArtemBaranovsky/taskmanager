
## Setting up environment

To run the project please come through following steps:
- Run docker containers: **composer docker-up** or **./vendor/bin/sail up**
- Run configuration composer script: **composer setproject**
- Start mailing queue via composer script: **run-queue**, or using a console: **php artisan queue:work --queue=mail-queue**
- Ready to go.

### Predefined creds for testing:
        'name'      => 'Test User',
        'email'     => 'test@test.com',
        'password'  => Hash::make('test'),
        'api_token' => 'v3OGwOdUfeYMv81E3bycXy2Cwz0DoyaC24HQIapVd9vGXp3qJP1Mb2lEHT2v',
