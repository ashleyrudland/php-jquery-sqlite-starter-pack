# PHP jQuery SQLite Starter Pack

This project was inspired by a recent [Lex Fridman podcast featuring Pieter Levels](https://www.youtube.com/watch?v=oFtjKbXKqbg), where they discussed the simplicity and effectiveness of using basic tech stacks for building successful products.

## Goal

The main objectives of this repository are:

1. To demystify the use of a simple tech stack consisting of PHP, SQLite, and jQuery.
2. To demonstrate that this straightforward approach can handle scale effectively.

We've achieved impressive results:

- 330,000 writes per second on a Vultr NVMe VPS ($12/mo) - [Live Demo](https://php-nvme.ashleyrudland.com/)
- 180,000 writes per second on a Hetzner entry VPS (€3.30/mo) - [Live Demo](https://phpboss.ashleyrudland.com/)

## Single File Architecture

One of the key features of this project is that everything runs in a single `index.php` file. This file handles multiple routes:

- `/`: The home page
- `/api/db-test`: Runs and returns the database performance test results
- `/api/get-capacity`: Returns the VPS capacity information
- `/api/up`: A simple endpoint to check if the service is up

To add new routes, simply extend the switch statement in the `index.php` file:

```php
switch ($path) {
case '/your-new-route':
handleYourNewRoute();
break;
// ... existing routes ...
}
function handleYourNewRoute() {
// Your new route logic here
}
```

## Key Features

- Single file (`index.php`) handles all routing and logic
- SQLite database with optimized settings for performance
- Cross-platform VPS capacity detection (works on both Linux and macOS)
- Caching mechanism for database test results
- Simple HTML/CSS with Tailwind CSS for styling
- jQuery for asynchronous API calls and DOM manipulation

## Getting Started

1. Clone this repository
2. Ensure you have PHP and SQLite installed on your system
3. Run `php -S localhost:8000` in the project directory
4. Visit `http://localhost:8000` in your browser

## Production Deployment

The code includes logic for production deployment:

- Set the `IS_PROD` environment variable to 'y' for production mode
- In production, the SQLite database is stored in `/data/database.sqlite`
- Error reporting is disabled in production for security

## Conclusion

To all the indie hackers out there: may this project inspire you to build great things with simple tools. Remember, it's not always about using the latest and greatest technology, but about solving real problems efficiently.

If you found this useful, feel free to follow me on X [@ashleyrudland](https://x.com/ashleyrudland) for more insights and projects.

Good luck with your ventures!
