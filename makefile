APP_PORT ?= 8000

dev:
	php -S 127.0.0.1:$(APP_PORT) -t public public/index.php
