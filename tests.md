# Running Unit Tests
A seperate docker-compose script has been added for unit testing purposes. It's not currently involved in any automatic
processes, and still requires vendor dependencies set.

```
docker-compose -f .\docker-compose.test.yml up --build --abort-on-container-exit --exit-code-from=sut
```

Exit code 0 indicates success, with any other code being a fail. Check for the response log `sut_1` for information from the procedure. Improvements to this procedure are welcome.