# signed-s3-links
The signed-s3-links WordPress plugin allows post authors to publish signed links to otherwise inaccessible S3 content.

## Installation

- [Install AWS PHP SDK.](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_installation.html)
- Set up credentials....

## Usage

### Settings

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

**TODO:** filter directory listing to files in exact directory listing.

**TODO:** provide dictionary for link text

## Testing

Running `phpunit` in the top-level directory....

## Linting

```
phpcs --standard=WordPress tests/test*php
phpcbf --standard=WordPress *.php tests/test*php
```