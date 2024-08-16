Rammewerk Request
======================

Early beta, more info will come.

New: Get type safe inputs, like `inputString('foo')` or `inputInt('foo')`.

Getting Started
---------------

```php
use Rammewerk\Component\Request\Request;

$request = new Request();

// Get all inputs
$request->all();

// Get server data
$request->server('HTTP_HOST');

// Get cookie data
$request->cookie('foo');

// Get file data
$request->file('foo');

// Get input data
$request->input('foo');

# Type safe inputs
$request->inputString('foo'); // Returns string|null
$request->inputInt('foo'); // Returns int|null
$request->inputFloat('foo'); // Returns float|null
$request->inputBool('foo'); // Returns bool
$request->inputArray('foo'); // Returns array|null
$request->inputDateTime('foo'); // Returns \DateTimeImmutable|null
$request->inputEmail('foo'); // Returns validated email string|null

# CSRF check
$request->validate_csrf('foo'); // Throws TokenMismatchException if token is invalid

# Session
$request->session->start();
$request->session->set('foo', 'bar');
$request->session->get('foo');
$request->session->all();
$request->session->remove('foo');
$request->session->regenerate();
$request->session->has('foo');
$request->session->regenerateCsrfToken();
$request->session->csrf_token();
$request->session->close();

$request->flash->success('foo');
$request->flash->error('foo');
$request->flash->info('foo');
$request->flash->warning('foo');
$request->flash->notify('foo');
$request->flash->get();

$request->path();
$request->rootDomain();
$request->domain();
$request->subdomain();
$request->isHttps();
$request->isSubdomain('foo');
$request->is('/img/*');
$request->getClientIp();

```