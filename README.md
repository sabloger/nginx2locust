# nginx2locust
Convert nginx access log to locust functions!!!

```
$ php extractor.php m.log report/funcs.py app_v2.php --locust "--locust-cookie=self.get_cookie()"
```
