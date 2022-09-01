# signed-s3-links
The signed-s3-links (SS3L) WordPress plugin allows post authors to publish signed links to otherwise inaccessible S3 content.

## Installation

- SS3L is targeted for PHP 7.4.30 and WordPress 6.0.
- [Install AWS PHP SDK.](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_installation.html)
- [Check your AWS credentials.](https://docs.aws.amazon.com/cli/latest/userguide/cli-configure-files.html)  SS3L will use the server-local credentials to authenticate with S3 services. 

## Usage

### Settings

In your WordPress installation under the plugin settings, go to the 
signed-s3-links settings and make sure your region and credentials profile
matches what you expect.

### Displaying a link

You can insert a signed hyperlink to a object stored under S3 with the markup in your post text
```
[ss3_ref my-s3-bucket-name/some/key/file.txt title="read this"]
```
The title parameter is optional.
In its absence, the plugin will use the object filename as the href text.

### Displaying a directory listing

You can display a directory listing using
```
[ss3_dir my-s3-bucket-name/some/key/]
```
which will render an HTML list of signed links titled by the corresponding object file names.

**TODO:** provide dictionary for link text

## Testing

- Set up unit tests with `./bin/install-wp-tests.sh wordpress_test your-user 'your-password' localhost 6.0`
- Run the unit tests with `composer exec phpunit`

## Linting

```
phpcs --standard=WordPress tests/test*php
phpcbf --standard=WordPress *.php tests/test*php
```
