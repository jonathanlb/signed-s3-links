# signed-s3-links
The signed-s3-links (SS3L) WordPress plugin allows post authors to publish signed links to otherwise inaccessible S3 content.

## Installation

- SS3L should work with PHP 9.0 and WordPress 6.0.  The [unit-test framework](https://phpunit.de/) bundled here, however, [only runs with PHP 7.4.30.](https://github.com/jonathanlb/signed-s3-links/issues/4)
- [Install AWS PHP SDK.](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_installation.html)  Running `composer update` using [composer.json](composer.json) should work if you have [composer installed.](https://getcomposer.org)
- [Check your AWS credentials.](https://docs.aws.amazon.com/cli/latest/userguide/cli-configure-files.html)  SS3L will use server-local credentials to authenticate with S3 services. 
  - By default, SS3L will look for credentials profiles in the `$HOME/.aws/credentials` file.  You can specify an alternate directory under the settings menu with "Path to credentials file" using either an absolute path begining with /, or one relative to the SS3L plugin directory.

## Usage

### Settings

In your WordPress installation under the plugin settings, go to the 
signed-s3-links settings and make sure your region and credentials profile
matches what you expect.

### Displaying a link

You can insert a signed hyperlink to a object stored under S3 with the markup in your post text
```
[ss3_ref my-s3-bucket-name/some/key/file.txt title="read this" region=us-west-2]
```
The title and region parameters are optional.
In the absence of title, the plugin will use the object filename as the href text.
Omitting region will create a link using the region stored in the plugin settings.

### Displaying a directory listing

You can display a directory listing using
```
[ss3_dir my-s3-bucket-name/some/key titles="index.json"]
```
which will render an HTML list of signed links titled by the corresponding object file names.
The optional `titles` parameter specifies a JSON file containing a dictionary mapping names (without the key prefix) to titles to print.
In the absence of a title entry or title dictionary, objects will list as their name under the key.

Here is an example titles dictionary:
```
{
	"2022_09_08_notes.pdf": "Emergency meeting minutes",
	"2022_09_01_agenda.html": "Emergency meeting agenda"
}
```

The titles dictionary object will not be printed, nor will objects nested in keys beneath the key specified.

The `ss3_dir` shortcode also takes the `region` optional parameter to override
the global default.

## Testing

- Set up unit tests with `./bin/install-wp-tests.sh wordpress_test your-user 'your-password' localhost 6.0`
- Run the unit tests with `composer exec phpunit`

## Linting

```
phpcs --standard=WordPress tests/test*php
phpcbf --standard=WordPress *.php tests/test*php
```
