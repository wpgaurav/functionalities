# Plugin Check Report

**Plugin:** Functionalities
**Generated at:** 2026-01-10 15:28:31


## `includes/features/class-link-management.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 346 | 38 | WARNING | WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound | Hook names invoked by a theme/plugin should start with the theme/plugin prefix. Found: &quot;gtnf_exception_domains&quot;. |  |
| 347 | 38 | WARNING | WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound | Hook names invoked by a theme/plugin should start with the theme/plugin prefix. Found: &quot;gtnf_exception_urls&quot;. |  |

## `includes/features/class-redirect-manager.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 358 | 10 | WARNING | WordPress.Security.SafeRedirect.wp_redirect_wp_redirect | wp_redirect() found. Using wp_safe_redirect(), along with the &quot;allowed_redirect_hosts&quot; filter if needed, can help avoid any chances of malicious redirects within code. It is also important to remember to call exit() after a redirect so that no other unwanted code is executed. | [Docs](https://developer.wordpress.org/reference/functions/wp_safe_redirect/) |
| 437 | 18 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 437 | 58 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 438 | 18 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 438 | 48 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 439 | 18 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 439 | 43 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 465 | 21 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 465 | 52 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 468 | 15 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 469 | 44 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 469 | 44 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;from&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 471 | 15 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 472 | 34 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 472 | 34 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;to&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 474 | 15 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 475 | 29 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 502 | 16 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 502 | 47 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 524 | 16 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 524 | 47 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 550 | 18 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 550 | 49 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 550 | 49 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_POST[&#039;json&#039;] |  |

## `includes/admin/class-admin.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 54 | 58 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;nonce&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 54 | 58 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_POST[&#039;nonce&#039;] |  |
| 66 | 57 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;url&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 85 | 58 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;nonce&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 85 | 58 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_POST[&#039;nonce&#039;] |  |
| 97 | 56 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_POST[&#039;content&#039;] |  |
| 117 | 10 | ERROR | WordPress.WP.AlternativeFunctions.file_system_operations_is_writable | File operations should use WP_Filesystem methods instead of direct PHP filesystem calls. Found: is_writable(). |  |
| 158 | 58 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;nonce&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 158 | 58 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_POST[&#039;nonce&#039;] |  |
| 336 | 18 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |
| 336 | 51 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |
| 337 | 20 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |
| 337 | 55 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |
| 382 | 28 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |
| 382 | 63 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |
| 383 | 18 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |
| 383 | 51 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |
| 457 | 38 | ERROR | WordPress.WP.I18n.UnorderedPlaceholdersText | Multiple placeholders in translatable strings should be ordered. Expected "%1$s, %2$s", but got "%s, %s" in 'Functionalities v%s \| Visit %s for more information.'. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#variables) |
| 666 | 134 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 678 | 130 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 690 | 130 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 702 | 134 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 714 | 130 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 726 | 132 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 738 | 131 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 750 | 130 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 762 | 135 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 902 | 111 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 924 | 114 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 947 | 117 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1029 | 117 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1044 | 72 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$sel'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1057 | 117 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1068 | 117 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1079 | 113 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1094 | 72 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$sel'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1107 | 111 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1118 | 108 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1129 | 109 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1140 | 117 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1198 | 110 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1216 | 114 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1374 | 105 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1507 | 104 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1520 | 118 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1533 | 115 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1546 | 119 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1568 | 122 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1601 | 73 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$sel'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1634 | 175 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$is_checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1657 | 73 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$sel'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1722 | 107 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1788 | 77 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$sel'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1847 | 118 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1869 | 189 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$is_checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1894 | 128 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1933 | 133 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1957 | 129 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 1996 | 129 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2020 | 126 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2033 | 128 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2046 | 129 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2059 | 132 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2096 | 127 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2141 | 120 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2154 | 136 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2167 | 134 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2180 | 135 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2193 | 137 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2219 | 136 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2232 | 136 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2245 | 133 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2258 | 138 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2271 | 133 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2284 | 144 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2297 | 134 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2310 | 131 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2346 | 117 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2405 | 121 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2417 | 121 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2589 | 45 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found 'wp_create_nonce'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2661 | 122 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 2888 | 49 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found 'wp_create_nonce'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3030 | 124 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3035 | 121 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3040 | 122 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3085 | 109 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3095 | 174 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$is_checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3548 | 36 | ERROR | WordPress.WP.I18n.MissingTranslatorsComment | A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a "translators:" comment on the line above to clarify the meaning of the placeholders. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions) |
| 3548 | 86 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$total_items'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3566 | 48 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$is_visible'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3566 | 88 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3566 | 118 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$page'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3585 | 122 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3589 | 122 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3593 | 131 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3613 | 135 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$p'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3614 | 32 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$p'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3621 | 36 | ERROR | WordPress.WP.I18n.MissingTranslatorsComment | A function call to esc_html__() with texts containing placeholders was found, but was not accompanied by a "translators:" comment on the line above to clarify the meaning of the placeholders. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions) |
| 3621 | 93 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$total_pages'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3672 | 38 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$per_page'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3673 | 41 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$total_items'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3674 | 40 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 3882 | 121 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$checked'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4247 | 45 | ERROR | WordPress.WP.I18n.MissingTranslatorsComment | A function call to _n() with texts containing placeholders was found, but was not accompanied by a "translators:" comment on the line above to clarify the meaning of the placeholders. | [Docs](https://developer.wordpress.org/plugins/internationalization/how-to-internationalize-your-plugin/#descriptions) |
| 4247 | 109 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$total_items'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4277 | 66 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4300 | 126 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4300 | 161 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$family'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4304 | 115 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4311 | 115 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4323 | 126 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4323 | 161 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$weight'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4327 | 126 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4327 | 167 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$weight_range'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4332 | 78 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4332 | 132 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4333 | 63 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4336 | 78 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4336 | 132 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4337 | 63 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4343 | 162 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4343 | 200 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$woff2'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4350 | 162 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4350 | 199 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$woff'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4456 | 40 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$i'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 4457 | 41 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$total_items'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 5186 | 172 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$time_ago'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 5383 | 29 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |
| 5383 | 65 | WARNING | WordPress.Security.NonceVerification.Recommended | Processing form data without nonce verification. |  |
| 5818 | 33 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$stats['completed']'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 5819 | 33 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$stats['total']'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 6028 | 49 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$stats['completed']'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 6028 | 70 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$stats['total']'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 6090 | 57 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$completed_class'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 6179 | 45 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$stats['completed']'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 6570 | 82 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$stats['total']'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 6574 | 82 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$stats['enabled']'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 6578 | 82 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$stats['hits']'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 6782 | 81 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;_wpnonce&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 6782 | 81 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_POST[&#039;_wpnonce&#039;] |  |
| 6882 | 44 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$icon['svg']'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |

## `assets/.DS_Store`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | hidden_files | Hidden files are not permitted. |  |

## `includes/features/class-task-manager.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 74 | 6 | WARNING | WordPress.PHP.DevelopmentFunctions.error_log_error_log | error_log() found. Debug code should not normally be used in production. |  |
| 560 | 56 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$stats['completed']'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 560 | 77 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$stats['total']'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 561 | 38 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$stats['percent']'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 564 | 86 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$stats['percent']'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 664 | 18 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 664 | 55 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 685 | 21 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 685 | 58 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 686 | 21 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 686 | 62 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 686 | 62 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;text&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 687 | 21 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 687 | 67 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 687 | 67 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;notes&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 713 | 22 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 713 | 59 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 714 | 22 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 714 | 59 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 717 | 15 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 718 | 45 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 718 | 45 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;text&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 720 | 15 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 721 | 50 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 721 | 50 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;notes&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 723 | 15 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 724 | 33 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 726 | 15 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 727 | 22 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 727 | 41 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 727 | 41 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;tags&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 727 | 41 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_POST[&#039;tags&#039;] |  |
| 727 | 72 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 727 | 72 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;tags&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 727 | 72 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_POST[&#039;tags&#039;] |  |
| 755 | 21 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 755 | 58 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 756 | 21 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 756 | 58 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 778 | 21 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 778 | 58 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 779 | 21 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 779 | 58 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 805 | 22 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 805 | 59 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 806 | 22 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 806 | 53 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 806 | 53 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;task_ids&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 806 | 53 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_POST[&#039;task_ids&#039;] |  |
| 829 | 18 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 829 | 50 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 829 | 50 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_POST[&#039;json&#039;] |  |
| 880 | 18 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 880 | 55 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 908 | 25 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 908 | 62 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 909 | 25 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |
| 909 | 52 | WARNING | WordPress.Security.NonceVerification.Missing | Processing form data without nonce verification. |  |

## `includes/features/class-snippets.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 237 | 22 | ERROR | WordPress.WP.EnqueuedResources.NonEnqueuedScript | Scripts must be registered/enqueued via wp_enqueue_script() |  |
| 256 | 22 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$header_code'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 310 | 18 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$footer_code'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 356 | 14 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$body_open_code'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |

## `includes/features/class-login-security.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 342 | 50 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$logo_url'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 370 | 46 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$bg_color'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 375 | 46 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$form_bg'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |

## `includes/admin/class-admin-ui.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 34 | 35 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$class'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 34 | 50 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$open_attr'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |
| 36 | 61 | ERROR | WordPress.Security.EscapeOutput.OutputNotEscaped | All output should be run through an escaping function (see the Security sections in the WordPress Developer Handbooks), found '$content'. | [Docs](https://developer.wordpress.org/apis/security/escaping/#escaping-functions) |

## `includes/class-github-updater.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | plugin_updater_detected | Plugin Updater detected. These are not permitted in WordPress.org hosted plugins. Detected: site_transient_update_plugins |  |
| 0 | 0 | WARNING | update_modification_detected | Plugin Updater detected. Detected code which may be altering WordPress update routines. Detected: pre_set_site_transient_update_plugins | [Docs](https://developer.wordpress.org/plugins/wordpress-org/common-issues/#update-checker) |
| 0 | 0 | WARNING | update_modification_detected | Plugin Updater detected. Detected code which may be altering WordPress update routines. Detected: _site_transient_update_plugins | [Docs](https://developer.wordpress.org/plugins/wordpress-org/common-issues/#update-checker) |

## `includes/features/class-content-regression.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 207 | 41 | WARNING | WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound | Hook names invoked by a theme/plugin should start with the theme/plugin prefix. Found: &quot;the_content&quot;. |  |
| 217 | 5 | WARNING | WordPress.PHP.DevelopmentFunctions.error_log_error_log | error_log() found. Debug code should not normally be used in production. |  |
| 416 | 41 | WARNING | WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound | Hook names invoked by a theme/plugin should start with the theme/plugin prefix. Found: &quot;the_content&quot;. |  |

## `includes/features/class-components.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 205 | 18 | ERROR | WordPress.WP.EnqueuedResources.NonEnqueuedStylesheet | Stylesheets must be registered/enqueued via wp_enqueue_style() |  |

## `readme.txt`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 0 | 0 | ERROR | no_plugin_readme | The plugin readme.txt does not exist. |  |

## `includes/features/class-misc.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 407 | 48 | ERROR | PluginCheck.CodeAnalysis.EnqueuedResourceOffloading.OffloadedContent | Found call to wp_enqueue_script() with external resource. Offloading scripts to your servers or any remote service is disallowed. |  |

## `includes/features/class-assumption-detection.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 1345 | 59 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;hash&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 1373 | 59 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;hash&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 1401 | 59 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;hash&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 1470 | 25 | WARNING | WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound | Hook names invoked by a theme/plugin should start with the theme/plugin prefix. Found: &quot;wp_head&quot;. |  |
| 1479 | 25 | WARNING | WordPress.NamingConventions.PrefixAllGlobals.NonPrefixedHooknameFound | Hook names invoked by a theme/plugin should start with the theme/plugin prefix. Found: &quot;wp_footer&quot;. |  |
| 1488 | 5 | WARNING | WordPress.PHP.DevelopmentFunctions.error_log_error_log | error_log() found. Debug code should not normally be used in production. |  |

## `includes/features/class-svg-icons.php`

| Line | Column | Type | Code | Message | Docs |
| --- | --- | --- | --- | --- | --- |
| 476 | 52 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;nonce&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 476 | 52 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_POST[&#039;nonce&#039;] |  |
| 489 | 56 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;name&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 490 | 45 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_POST[&#039;svg&#039;] |  |
| 547 | 52 | WARNING | WordPress.Security.ValidatedSanitizedInput.MissingUnslash | $_POST[&#039;nonce&#039;] not unslashed before sanitization. Use wp_unslash() or similar |  |
| 547 | 52 | WARNING | WordPress.Security.ValidatedSanitizedInput.InputNotSanitized | Detected usage of a non-sanitized input variable: $_POST[&#039;nonce&#039;] |  |
