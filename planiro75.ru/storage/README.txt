Каталог для вложений задач (создаётся автоматически).

Каталог storage должен быть в КОРНЕ проекта (рядом с includes/ и public/), не отдельно от сайта.

Найти корень:
  find ~ -maxdepth 5 -path '*/includes/bootstrap.php' 2>/dev/null

Затем в этом каталоге:
  mkdir -p storage/uploads var/log
  chmod -R 775 storage var/log

Пример для planiro75.ru (REG.RU / server207):
  /var/www/u3508000/data/www/planiro75.ru/storage/uploads

Проверка (под админом, после входа на сайт):
  GET /public/api/upload_diag.php
