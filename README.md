# Bones API

Starter app to build a RESTful API using the [Bones framework](https://github.com/bayfrontmedia/bones).

- [License](#license)
- [Author](#author)
- [Requirements](#requirements)
- [Installation](#installation)
- [Usage](#usage)

## License

This project is open source and available under the [MIT License](LICENSE).

## Author

John Robinson, [Bayfront Media](https://www.bayfrontmedia.com)

## Requirements

* PHP >= 7.2.0
* JSON PHP extension

## Installation

### Install using Composer

To install using Composer:

```
composer create-project bayfrontmedia/bones-api project-name
```

### Create a symbolic link

If not already existing in `/public`, a symbolic link must be created to map to the `/storage/public` directory.
From the command line, navigate to the `/public` directory and type:

```
ln -s ../storage/public storage
```

Or, change "storage" to whatever you want the public storage directory to be named.

### Configure the app

If existing, rename the file `.env.example` to `.env`, then modify the environment variables as needed.

Modify the configuration files in the `/config` directory as needed.

**Important:** Be sure to define a unique `APP_KEY` in the `.env` file. A cryptographically secure app key can be created from the command line:

```
php /path/to/resources/cli.php
```

### Setup a cron job

If cron jobs will be used, add a new entry to your crontab to run every minute:

```
* * * * * /path/to/php/bin /path/to/resources/cron.php > /dev/null 2>&1
```

Now, your server will check the file every minute, and the Cron scheduler service will only run the jobs that are due, according to their schedule.
All output from the cron jobs will be saved to the output file specified in `/config/cron.php`.

## Usage

Navigate to the public root of your application in a browser. You should see the message "Bones v* is successfully installed".

You are now ready to begin building your application.

Documentation for this application can be found [here](_docs/README.md).

For further documentation, see the [Bones](https://github.com/bayfrontmedia/bones) repository.