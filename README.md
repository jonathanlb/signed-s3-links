# signed-s3-links
The signed-s3-links (SS3L) WordPress plugin allows post authors to publish signed links to otherwise inaccessible S3 content.

## Installation

- SS3L should work with PHP 8.0 and WordPress 6.0.  The [unit-test framework](https://phpunit.de/) bundled here, however, [only runs with PHP 7.4.30.](https://github.com/jonathanlb/signed-s3-links/issues/4)
- [Install AWS PHP SDK.](https://docs.aws.amazon.com/sdk-for-php/v3/developer-guide/getting-started_installation.html)  Running `composer update` using [composer.json](composer.json) should work if you have [composer installed.](https://getcomposer.org)
- [Check your AWS credentials.](https://docs.aws.amazon.com/cli/latest/userguide/cli-configure-files.html)  SS3L will use server-local credentials to authenticate with S3 services. 
  - By default, SS3L will look for credentials profiles in the plugin installation directory for the `credentials` file and use the `default` profile.  You can specify an alternate directory under the settings menu with "Path to credentials file" using either an absolute path begining with /, or one relative to the SS3L plugin directory.

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

#### Optional named parameters:

- `class` provides styling classes for the link.
- `div_class` provides class style for an enclosing div.  If no value is provide, the hyperlink appears naked without an enclosing div.
- `div_style` provides style for the enclosing div. If no class or style value is provided, the hyperlink appears naked without an enclosing div.
- `id` provides a reference to the hyperlink.
- `style` provides css style overrides to the hyperlink.

### Displaying a directory listing

You can display a directory listing using
```
[ss3_dir my-s3-bucket-name/some/key titles="index.json"]
```
which will render an HTML list of signed links titled by the corresponding object file names.

#### Optional named parameters:

- `div_class` provides class for an enclosing div. If no class or style value is provided, the list appears naked without an enclosing div.
- `div_style` provides style for the enclosing div. If no class or style value is provided, the list appears naked without an enclosing div.
- `a_class`, `li_class`, `ul_class` style the links using a class, list elements, and unordered list and reference the list, respectively.
- `id` provide. a reference to the unordered list.
- `region` overrides the global default AWS region.
- `style` provides css style overrides to the hyperlink.
- `titles` specifies a JSON file containing a dictionary mapping names (without the key prefix) to titles to print.  In the absence of a title entry or title dictionary, objects will list as their name under the key.

Here is an example titles dictionary:
```
{
	"2022_09_08_notes.pdf": "Emergency meeting minutes",
	"2022_09_01_agenda.html": "Emergency meeting agenda"
}
```

The titles dictionary object will not be printed, nor will objects nested in keys beneath the key specified.

### Using the media player

You can embed a signed link into a HTML5 media player
```
[ss3_audio my-s3-bucket/tunebook/song.mp3 style="width: 100%;" class="wp-audio-shortcode" title="Some Song to Play"]
```

#### Optional named parameters:

- `class` provide styling classes for the audio player.
- `id` provide a reference to the audio player.
- `title` enclose the player in a caption with the given title.
- `style` provides css style overrides to the audio player, e.g. `"width: 100%;"`.

## Troubleshooting

### Using a Visual Editor
If you're using a visual editor, like [Divi](https://www.elegantthemes.com/gallery/divi/), either

- use the "text" editing mode, as opposed to the "visual" mode option in the upper-right side of the panel,
- embed the shortcode inside a Code module container.

Divi will wrap certain tokens, like "class", in span elements, preventing WordPress from interpretting shortcode arguments.
Also, the visual editor can be slow to apply CSS class styling to
the shortcodes.
Be patient.
Test outside of the editor.
Check the `post_content` column in the `wp_posts` table using MySQL.

### AWS Credentials
If you get an error reading the AWS credentials file from the home directory,
make sure that your credentials file is readable by WordPress and 
referenced from the Signed-S3-Links settings menu.

Make sure that the credentials file is not readable by the public, e.g. the link `https://yourhost.me/wp-content/plugins/signed-s3-links/credentials` , is not viewable. Options to protect the files might be to move the file elsewhere on your host, add a restriction to an `.htaccess` file, or use your provider's access tools.

## Testing
  - For testing, we use the @wordpress/env npm package.
    - From the plugin directory, run `npm i`
    - I'm sure I've forgotten all the steps to set up [wp-env](https://www.npmjs.com/package/@wordpress/env), but make sure Docker is running...
  - `npm run wp-env-start`
  - Make sure that there is a valid `credentials` file with a `default` profile in the default location, the plugin directory (this one).  The repository will not provide one.
  - `npm t` # ad nausea...
  - `npm run wp-env-stop`

## Linting
```
npm run lint
npm run sniff
```
