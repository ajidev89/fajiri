setup:
	@make build
	@make start
build:
	docker compose build --no-cache --force-rm
stop:
	docker compose stop
start:
	@make up 
	@make composer-update
	@make data
	docker exec -d tecbank php artisan reverb:start
	docker exec tecbank bash -c "service cron start"
	# docker exec tecbank bash -c "service supervisord start"
up:
	docker compose up -d
composer-update:
	docker exec tecbank bash -c "composer update"
clear:
	docker exec tecbank bash -c "echo '' > storage/logs/laravel.log"
data:
	docker exec tecbank bash -c "php artisan migrate"
artisan: ## Run artisan commands (usage: make artisan CMD="migrate")
	docker exec tecbank bash -c "php artisan $(CMD)"
