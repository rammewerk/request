Rammewerk Request
======================

Rammewerk Request is a PHP component designed to simplify handling HTTP requests, sessions, file uploads, and flash messages. This component offers a structured way to interact with request data, including inputs, cookies, server variables, and uploaded files, while also providing robust session management and flash messaging capabilities.

## Features

- **Request Handling**: Easily access and manipulate request data such as inputs, cookies, and server variables.
- **Session Management**: Start, manage, and destroy sessions securely with built-in CSRF token handling.
- **File Upload Handling**: Normalize and manage uploaded files through an intuitive API.
- **Flash Messages**: Set and retrieve flash messages for user notifications across requests.
- **Type-Safe Input Methods**: Retrieve inputs in a type-safe manner (e.g., string, integer, float, boolean, array, etc.).
- **Domain and URI Checks**: Determine request paths, domains, subdomains, and check for HTTPS.

## Installation

To install the Rammewerk Request component, you can use Composer:

```bash
composer require rammewerk/request
```

## Usage

### Basic Request Handling

```php
use Rammewerk\Component\Request\Request;

// Initialize Request
$request = new Request();

// Retrieve a specific input value
$username = $request->input('username');

// Retrieve all inputs
$allInputs = $request->all();
```

### Session Management
```php

// Set a session value
$request->session->set('user_id', 42);

// Get a session value
$userId = $request->session->get('user_id');

// Regenerate CSRF Token
$request->session->regenerateCsrfToken();
```

### File Upload Handling
```php

// Retrieve an uploaded file
$uploadedFile = $request->file('profile_picture');

if ($uploadedFile) {
    // Handle the uploaded file
    $uploadedFile->move('/path/to/folder', 'name.jpg' );
}
```

### Flash Messages
```php

// Set a success message
$request->flash->success('Your profile has been updated!');

// Get and display flash messages
foreach ($request->flash->get() as $message) {
    echo $message->type . ': ' . $message->message;
}
```

### Domain and URI Checks
```php
// Check if the request is over HTTPS
if ($request->isHttps()) {
    echo "Secure connection";
}

// Get the request path
$path = $request->path();

// Get the root domain
$rootDomain = $request->rootDomain();
```

### Type-Safe Input Retrieval

```php
// Retrieve a string input
$username = $request->inputString('username');

// Retrieve an integer input
$age = $request->inputInt('age');

// Retrieve a boolean input
$isActive = $request->inputBool('is_active');

// Retrieve an array input
$data = $request->inputArray('data');

// Retrieve a date input
$date = $request->inputDateTime('date', 'Y-m-d H:i:s');

// Retrieve an email input, validates the email address. null if not valid
$email = $request->inputEmail('email');
```

## Contributing
If you would like to contribute to the Rammewerk Request component, please feel free to submit a pull request. All contributions are welcome!

## License
Rammewerk Request is open-sourced software licensed under the MIT license.