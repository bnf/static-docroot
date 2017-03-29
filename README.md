Static DOCUMENT\_ROOT for symlink based deployments
===================================================

This package acts as composer plugin in order to automatically patch
`$_SERVER['DOCUMENT_ROOT']` and `$_SERVER['SCRIPT_FILENAME']`to their realpath
so that they do not contain symlinks.
That is to ensure that they are static and stable. That means we want their
destination to not change during one request. That would happen when they
contain symlinks which may change during one request due to a deployment.

The behavior of this plugin can be influenced by configuration in the `extra`
section of the root `composer.json`

```
  "extra": {
      "bnf/static-docroot": {
          "web-dir": "public"
      }
  }
```

#### `web-dir`
You can specify a relative path from the base directory, where the public
document root should be located.

*The default value* is derived from `extra|typo3/cms|web-dir` or if that is
unset, `"web"` as last resort.
That means if you have already configured the `typo3/cms` `web-dir`,
you do not need to add the `bnf/static-docroot` section.
```
  "extra": {
      "typo3/cms": {
          "web-dir": "web"
      }
  }
```
