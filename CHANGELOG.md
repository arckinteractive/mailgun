<a name="2.0.2"></a>
## [2.0.2](https://github.com/arckinteractive/mailgun/compare/2.0.1...v2.0.2) (2016-08-22)




<a name="2.0.1"></a>
## [2.0.1](https://github.com/arckinteractive/mailgun/compare/2.0.0...v2.0.1) (2016-08-18)


### Bug Fixes

* **composer:** handle cases when plugin is installed as another plugins depdendency ([a7cfa56](https://github.com/arckinteractive/mailgun/commit/a7cfa56))
* **inbound:** add new content items to river ([29c2b64](https://github.com/arckinteractive/mailgun/commit/29c2b64))

### Features

* **security:** sanitize subject and body of received emails ([9754286](https://github.com/arckinteractive/mailgun/commit/9754286))



<a name="2.0.0"></a>
# 2.0.0 (2016-08-18)


### Bug Fixes

* **test:** fix call to non-existent function in email testing ([ad4d637](https://github.com/arckinteractive/mailgun/commit/ad4d637))

### Features

* **deps:** upgrade to Mailgun 2.1 ([8f02499](https://github.com/arckinteractive/mailgun/commit/8f02499))
* **inbound:** emails targeted at groups are now stored as new group discussions ([b82c7bb](https://github.com/arckinteractive/mailgun/commit/b82c7bb))
* **inbound:** improves handling of inbound emails ([2813a83](https://github.com/arckinteractive/mailgun/commit/2813a83))
* **inbound:** now handles incoming email attachments ([5c45a25](https://github.com/arckinteractive/mailgun/commit/5c45a25))
* **notifications:** automate tokenization of outgoing notifications ([f50ed2c](https://github.com/arckinteractive/mailgun/commit/f50ed2c))
* **test:** move test page handler to a resource view ([cbca448](https://github.com/arckinteractive/mailgun/commit/cbca448))


### BREAKING CHANGES

* deps: Now uses Mailgun 2.1, which requires an HttpClient adapter



