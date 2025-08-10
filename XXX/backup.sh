# mysql -u root -p$(grep -oP 'DB_PASSWORD_ROOT=\K[^ ]+' "/var/www/visagov/.env") visagov_b < /home/backup/2023-09-15/visagov_b_2023-09-15_01:00.sql
# /home/backup/backup.sh



DATE=$(date +%Y-%m-%d)
BACKUP_DIR="/home/backup"
MAX_DAYS=5

# Функция для отправки сообщения в Telegram

# Функция для создания резервной копии
create_backup() {
    local project=$1
    local db_config_path="/var/www/${project}/.env"
    local db_host=$(grep -oP 'DB_HOST=\K[^ ]+' "$db_config_path")
    local db_database=$(grep -oP 'DB_DATABASE=\K[^ ]+' "$db_config_path")
    local db_password_root=$(grep -oP 'DB_PASSWORD_ROOT=\K[^ ]+' "$db_config_path")
    local telegram_token=$(grep -oP 'TELEGRAM_TOKEN=\K[^ ]+' "$db_config_path")
    
    local backup_name="${project}_backup_${DATE}.zip"
    local sql_backup_name="${project}_b_${DATE}.sql"
    
    # Архивируем код
    zip -rq "$BACKUP_DIR/$DATE/$backup_name" "/var/www/${project}/"
    
    # Резервная копия базы данных
    mysqldump --single-transaction --routines --triggers --host="$db_host" --user="root" --password="$db_password_root" "$db_database" > "$BACKUP_DIR/$DATE/$sql_backup_name"
    
    # Проверяем на ошибки mysqldump
    if [[ $? -ne 0 ]]; then
        echo "mysqldump для $project не удалась"
    fi

}

# Рассчитываем использование дискового пространства
DISK_SPACE=$(df -h | grep /dev/sda1 | awk '{print $5}' | awk '{s+=$1} END {print s}')

# Создаем директорию для резервных копий
mkdir -p "$BACKUP_DIR/$DATE"

# Проекты для резервного копирования
projects=("takihab" "tandooria" "dispatcher-hub")

for project in "${projects[@]}"; do
    create_backup "$project"
done

# Удаляем старые резервные копии
cd "$BACKUP_DIR" || exit
for dir in */; do
    if [[ -d "$dir" && ! -L "$dir" ]]; then
        dir_date=$(date -d "$(stat -c %y "$dir")" +%s)
        current_date=$(date +%s)
        days_diff=$(( (current_date - dir_date) / (60 * 60 * 24) ))
        
        if [[ "$days_diff" -gt "$MAX_DAYS" ]]; then
            rm -rf "$dir"
            echo "Удалена директория $dir"
        fi
    fi
done



TOKEN="6338138495:AAHNcBCKeh-opZ9GHWkpu9hFh_-O2YFVP0g"
CHAT_ID="5563569258" 

SUBJECT="TAKI"

DISK_SPACE=$(df -h | grep /dev/sda1 | awk '{print $5}' | awk '{s+=$1} END {print s}')
MESSAGE="Backup was successfully created\nDisk space used: $DISK_SPACE%"

curl -s --header 'Content-Type: application/json' --request 'POST' --data "{\"chat_id\":\"${CHAT_ID}\",\"text\":\"${SUBJECT}\n${MESSAGE}\"}" "https://api.telegram.org/bot${TOKEN}/sendMessage"


