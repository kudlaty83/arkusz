-- Arkusz 1.1.1 — kompletny schemat bazy, rewizja 3.
-- Historia zastosowania jest zapisywana przez wersjonowane migracje aplikacji.
-- Uruchamiać w wybranej, pustej bazie danych. Celowo bez DROP TABLE.
-- Wszystkie daty i godziny aplikacja zapisuje w UTC.
-- Kwoty: netto, DECIMAL, waluta na zamówieniu; wymiary w mm, gramatura w g/m².
-- Statusy i kody słownikowe należy uzupełnić przed użyciem aplikacji.
-- Reguły wielotabelowe (np. zgodność pozycji i części) sprawdza API w transakcji.

SET NAMES utf8mb4;

CREATE TABLE users (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    login VARCHAR(100) NOT NULL UNIQUE,
    display_name VARCHAR(200) NOT NULL,
    password_hash VARCHAR(255) NOT NULL,
    theme ENUM('green', 'beige', 'gray', 'black', 'beige_light') NOT NULL DEFAULT 'green',
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE customers (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(255) NOT NULL,
    tax_id VARCHAR(32),
    email VARCHAR(254),
    phone VARCHAR(50),
    address_text TEXT,
    notes TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    INDEX idx_customers_name (name),
    INDEX idx_customers_tax_id (tax_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE product_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Rodzaj papieru oddzielony od wariantu gramatury i konkretnego produktu.
CREATE TABLE paper_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name VARCHAR(150) NOT NULL UNIQUE,
    description TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE paper_variants (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    paper_type_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(200) NOT NULL,
    grammage_gsm SMALLINT UNSIGNED NOT NULL CHECK (grammage_gsm > 0),
    manufacturer VARCHAR(150),
    color VARCHAR(100),
    description TEXT,
    is_active BOOLEAN NOT NULL DEFAULT TRUE,
    FOREIGN KEY (paper_type_id) REFERENCES paper_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Przykładowe statusy: nowe, zatwierdzone, w produkcji, gotowe, wydane, anulowane.
CREATE TABLE order_statuses (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(100) NOT NULL,
    is_terminal BOOLEAN NOT NULL DEFAULT FALSE,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    is_active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE orders (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_number VARCHAR(50) NOT NULL UNIQUE,
    customer_id BIGINT UNSIGNED NOT NULL,
    -- Kopia danych klienta z chwili przyjęcia zamówienia.
    customer_name VARCHAR(255) NOT NULL,
    customer_tax_id VARCHAR(32),
    customer_address TEXT,
    contact_name VARCHAR(200),
    contact_email VARCHAR(254),
    contact_phone VARCHAR(50),
    title VARCHAR(255) NOT NULL,
    status_id BIGINT UNSIGNED NOT NULL,
    priority ENUM('low', 'normal', 'high', 'urgent') NOT NULL DEFAULT 'normal',
    currency CHAR(3) NOT NULL DEFAULT 'PLN',
    ordered_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    due_at DATETIME,
    completed_at DATETIME,
    delivery_method VARCHAR(100),
    delivery_address TEXT,
    -- Dodatkowe informacje i ustalenia dotyczące całego zamówienia.
    customer_notes TEXT,
    internal_notes TEXT,
    created_by BIGINT UNSIGNED NOT NULL,
    updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    FOREIGN KEY (customer_id) REFERENCES customers(id),
    FOREIGN KEY (status_id) REFERENCES order_statuses(id),
    FOREIGN KEY (created_by) REFERENCES users(id),
    INDEX idx_orders_status_due (status_id, due_at),
    INDEX idx_orders_customer_date (customer_id, ordered_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_items (
    -- Element wycofany ze specyfikacji pozostaje dostępny w historii.
    is_archived BOOLEAN NOT NULL DEFAULT FALSE,
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    line_number SMALLINT UNSIGNED NOT NULL,
    product_type_id BIGINT UNSIGNED NOT NULL,
    product_type_name VARCHAR(100) NOT NULL,
    name VARCHAR(255) NOT NULL,
    quantity INT UNSIGNED NOT NULL CHECK (quantity > 0),
    finished_width_mm DECIMAL(9,2) NOT NULL CHECK (finished_width_mm > 0),
    finished_height_mm DECIMAL(9,2) NOT NULL CHECK (finished_height_mm > 0),
    agreed_total_net DECIMAL(14,2) CHECK (agreed_total_net >= 0),
    tax_rate DECIMAL(5,2) CHECK (tax_rate BETWEEN 0 AND 100),
    -- Dodatkowe informacje do produktu.
    specification_notes TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (product_type_id) REFERENCES product_types(id),
    UNIQUE KEY uq_item_line (order_id, line_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Np. środek, okładka, wkładka. Strony i kolory opisują tę część produktu.
-- 0 oznacza stronę niezadrukowaną; offset: 1–5, cyfra: bw lub full_color.
CREATE TABLE item_components (
    -- Element wycofany ze specyfikacji pozostaje dostępny w historii.
    is_archived BOOLEAN NOT NULL DEFAULT FALSE,
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_item_id BIGINT UNSIGNED NOT NULL,
    name VARCHAR(150) NOT NULL,
    sort_order SMALLINT NOT NULL DEFAULT 0,
    page_count INT UNSIGNED NOT NULL DEFAULT 2 CHECK (page_count > 0),
    paper_variant_id BIGINT UNSIGNED NOT NULL,
    -- Migawka parametrów: zmiana katalogu nie zmienia starego zlecenia.
    paper_description VARCHAR(255) NOT NULL,
    grammage_gsm SMALLINT UNSIGNED NOT NULL CHECK (grammage_gsm > 0),
    print_technique ENUM('offset', 'digital') NOT NULL,
    offset_front_colors TINYINT UNSIGNED,
    offset_back_colors TINYINT UNSIGNED,
    digital_front_mode ENUM('none', 'bw', 'full_color'),
    digital_back_mode ENUM('none', 'bw', 'full_color'),
    bleed_mm DECIMAL(5,2) NOT NULL DEFAULT 3 CHECK (bleed_mm >= 0),
    -- Dodatkowe informacje do konkretnej części technologicznej.
    notes TEXT,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id),
    FOREIGN KEY (paper_variant_id) REFERENCES paper_variants(id),
    CONSTRAINT chk_component_print CHECK (
        (print_technique = 'offset'
         AND offset_front_colors IS NOT NULL AND offset_back_colors IS NOT NULL
         AND offset_front_colors BETWEEN 0 AND 5 AND offset_back_colors BETWEEN 0 AND 5
         AND offset_front_colors + offset_back_colors > 0
         AND digital_front_mode IS NULL AND digital_back_mode IS NULL)
        OR
        (print_technique = 'digital'
         AND offset_front_colors IS NULL AND offset_back_colors IS NULL
         AND digital_front_mode IS NOT NULL AND digital_back_mode IS NOT NULL
         AND (digital_front_mode <> 'none' OR digital_back_mode <> 'none'))
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Konkretne farby offsetowe, np. CMYK lub Pantone; strona i numer zespołu.
CREATE TABLE component_inks (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    component_id BIGINT UNSIGNED NOT NULL,
    side ENUM('front', 'back') NOT NULL,
    ink_number TINYINT UNSIGNED NOT NULL CHECK (ink_number BETWEEN 1 AND 5),
    ink_name VARCHAR(100) NOT NULL,
    FOREIGN KEY (component_id) REFERENCES item_components(id),
    UNIQUE KEY uq_component_ink (component_id, side, ink_number)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Operacje obejmują przygotowalnię, druk, cięcie, falcowanie, oprawę, pakowanie itd.
CREATE TABLE operation_types (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(50) NOT NULL UNIQUE,
    name VARCHAR(150) NOT NULL,
    is_active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE production_tasks (
    -- Raporty poprzednich specyfikacji nie zwiększają wykonania nowej rewizji.
    plan_revision INT UNSIGNED NOT NULL DEFAULT 1,
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_item_id BIGINT UNSIGNED NOT NULL,
    component_id BIGINT UNSIGNED,
    operation_type_id BIGINT UNSIGNED NOT NULL,
    operation_name VARCHAR(150) NOT NULL,
    sequence_number SMALLINT UNSIGNED NOT NULL,
    status ENUM('pending', 'ready', 'in_progress', 'blocked', 'done', 'cancelled')
        NOT NULL DEFAULT 'pending',
    work_center_id BIGINT UNSIGNED,
    planned_quantity INT UNSIGNED NOT NULL CHECK (planned_quantity > 0),
    quantity_unit VARCHAR(30) NOT NULL,
    planned_start_at DATETIME,
    due_at DATETIME,
    started_at DATETIME,
    finished_at DATETIME,
    -- Parametry np. foliowanie matowe, liczba bigów, rodzaj oprawy.
    parameters JSON,
    instructions TEXT,
    version INT UNSIGNED NOT NULL DEFAULT 1,
    FOREIGN KEY (order_item_id) REFERENCES order_items(id),
    FOREIGN KEY (component_id) REFERENCES item_components(id),
    FOREIGN KEY (operation_type_id) REFERENCES operation_types(id),
    INDEX idx_tasks_queue (status, due_at),
    INDEX idx_tasks_item_sequence (order_item_id, sequence_number),
    INDEX idx_tasks_center (work_center_id, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Raporty przyrostowe: ilości z danego raportu, nie liczniki narastające.
-- Suma raportów daje wykonanie zadania. Status zmienia API w tej samej transakcji.
CREATE TABLE production_reports (
    -- Raporty poprzednich specyfikacji nie zwiększają wykonania nowej rewizji.
    plan_revision INT UNSIGNED NOT NULL DEFAULT 1,
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    task_id BIGINT UNSIGNED NOT NULL,
    reported_by BIGINT UNSIGNED NOT NULL,
    reported_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    good_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    waste_quantity INT UNSIGNED NOT NULL DEFAULT 0,
    work_minutes INT UNSIGNED NOT NULL DEFAULT 0,
    status_after ENUM('pending', 'ready', 'in_progress', 'blocked', 'done', 'cancelled') NOT NULL,
    notes TEXT,
    FOREIGN KEY (task_id) REFERENCES production_tasks(id),
    FOREIGN KEY (reported_by) REFERENCES users(id),
    INDEX idx_reports_task_time (task_id, reported_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

CREATE TABLE order_status_history (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    old_status_id BIGINT UNSIGNED,
    new_status_id BIGINT UNSIGNED NOT NULL,
    changed_by BIGINT UNSIGNED NOT NULL,
    changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    reason TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (old_status_id) REFERENCES order_statuses(id),
    FOREIGN KEY (new_status_id) REFERENCES order_statuses(id),
    FOREIGN KEY (changed_by) REFERENCES users(id),
    INDEX idx_order_history (order_id, changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Pliki poza bazą i publicznym katalogiem WWW; pobieranie przez autoryzowane API.
CREATE TABLE order_files (
    id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    order_id BIGINT UNSIGNED NOT NULL,
    order_item_id BIGINT UNSIGNED,
    original_name VARCHAR(255) NOT NULL,
    storage_key VARCHAR(255) NOT NULL UNIQUE,
    mime_type VARCHAR(150) NOT NULL,
    size_bytes BIGINT UNSIGNED NOT NULL,
    sha256 CHAR(64) NOT NULL,
    purpose ENUM('artwork', 'proof', 'approval', 'other') NOT NULL,
    revision INT UNSIGNED NOT NULL DEFAULT 1 CHECK (revision > 0),
    uploaded_by BIGINT UNSIGNED NOT NULL,
    uploaded_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    approved_by BIGINT UNSIGNED,
    approved_at DATETIME,
    approval_note TEXT,
    FOREIGN KEY (order_id) REFERENCES orders(id),
    FOREIGN KEY (order_item_id) REFERENCES order_items(id),
    FOREIGN KEY (uploaded_by) REFERENCES users(id),
    FOREIGN KEY (approved_by) REFERENCES users(id),
    CONSTRAINT chk_file_approval CHECK (
        (approved_by IS NULL AND approved_at IS NULL)
        OR (approved_by IS NOT NULL AND approved_at IS NOT NULL)
    )
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Założenia API:
-- * sprawdza zgodność component_id z order_item_id oraz order_item_id z order_id;
-- * kontroluje liczbę farb i dopuszcza component_inks wyłącznie dla offsetu;
-- * zapisuje status i historię atomowo, także początkowy status zamówienia;
-- * sprawdza uprawnienia i dozwolone przejścia statusów;
-- * zwiększa version przy edycji i wykrywa równoczesne zmiany;
-- * zatwierdzone pliki są niezmienne; kolejna wersja to nowy rekord;
-- * waliduje parametry JSON według rodzaju operacji;
-- * archiwizuje dane historyczne zamiast usuwać powiązane rekordy.

CREATE TABLE roles (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(100) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE user_roles (
 user_id BIGINT UNSIGNED NOT NULL, role_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(user_id, role_id),
 FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(role_id) REFERENCES roles(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE permissions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(80) NOT NULL UNIQUE, name VARCHAR(150) NOT NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE role_permissions (
 role_id BIGINT UNSIGNED NOT NULL, permission_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(role_id,permission_id),
 FOREIGN KEY(role_id) REFERENCES roles(id), FOREIGN KEY(permission_id) REFERENCES permissions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE work_centers (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 code VARCHAR(50) NOT NULL UNIQUE, name VARCHAR(150) NOT NULL,
 is_active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
ALTER TABLE production_tasks ADD FOREIGN KEY(work_center_id) REFERENCES work_centers(id);
CREATE TABLE user_work_centers (
 user_id BIGINT UNSIGNED NOT NULL, work_center_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(user_id,work_center_id),
 FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(work_center_id) REFERENCES work_centers(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE task_assignments (
 task_id BIGINT UNSIGNED NOT NULL, user_id BIGINT UNSIGNED NOT NULL,
 assigned_by BIGINT UNSIGNED NOT NULL, assigned_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 PRIMARY KEY(task_id,user_id), FOREIGN KEY(task_id) REFERENCES production_tasks(id),
 FOREIGN KEY(user_id) REFERENCES users(id), FOREIGN KEY(assigned_by) REFERENCES users(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE task_dependencies (
 task_id BIGINT UNSIGNED NOT NULL, depends_on_task_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(task_id,depends_on_task_id), CHECK(task_id <> depends_on_task_id),
 FOREIGN KEY(task_id) REFERENCES production_tasks(id),
 FOREIGN KEY(depends_on_task_id) REFERENCES production_tasks(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE task_status_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, task_id BIGINT UNSIGNED NOT NULL,
 old_status VARCHAR(30), new_status VARCHAR(30) NOT NULL,
 event_type ENUM('status','assignment','created','plan') NOT NULL DEFAULT 'status',
 changed_by BIGINT UNSIGNED NOT NULL, changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 reason TEXT, FOREIGN KEY(task_id) REFERENCES production_tasks(id),
 FOREIGN KEY(changed_by) REFERENCES users(id), INDEX(task_id,changed_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE feature_types (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, code VARCHAR(50) NOT NULL UNIQUE,
 name VARCHAR(150) NOT NULL, scope ENUM('item','component','both') NOT NULL DEFAULT 'both',
 description TEXT, is_active BOOLEAN NOT NULL DEFAULT TRUE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE feature_parameter_definitions (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, feature_type_id BIGINT UNSIGNED NOT NULL,
 code VARCHAR(50) NOT NULL, name VARCHAR(150) NOT NULL,
 value_type ENUM('text','number','boolean') NOT NULL DEFAULT 'text',
 unit VARCHAR(30), is_required BOOLEAN NOT NULL DEFAULT FALSE,
 UNIQUE(feature_type_id,code), FOREIGN KEY(feature_type_id) REFERENCES feature_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE item_features (
    -- Element wycofany ze specyfikacji pozostaje dostępny w historii.
    is_archived BOOLEAN NOT NULL DEFAULT FALSE,
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, order_item_id BIGINT UNSIGNED NOT NULL,
 feature_type_id BIGINT UNSIGNED NOT NULL, notes TEXT,
 FOREIGN KEY(order_item_id) REFERENCES order_items(id), FOREIGN KEY(feature_type_id) REFERENCES feature_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE component_features (
    -- Element wycofany ze specyfikacji pozostaje dostępny w historii.
    is_archived BOOLEAN NOT NULL DEFAULT FALSE,
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY, component_id BIGINT UNSIGNED NOT NULL,
 feature_type_id BIGINT UNSIGNED NOT NULL, notes TEXT,
 FOREIGN KEY(component_id) REFERENCES item_components(id), FOREIGN KEY(feature_type_id) REFERENCES feature_types(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE item_feature_values (
 item_feature_id BIGINT UNSIGNED NOT NULL, parameter_definition_id BIGINT UNSIGNED NOT NULL,
 value_text TEXT, value_number DECIMAL(14,4), value_boolean BOOLEAN,
 PRIMARY KEY(item_feature_id,parameter_definition_id),
 CHECK((value_text IS NOT NULL) + (value_number IS NOT NULL) + (value_boolean IS NOT NULL) = 1),
 FOREIGN KEY(item_feature_id) REFERENCES item_features(id),
 FOREIGN KEY(parameter_definition_id) REFERENCES feature_parameter_definitions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE component_feature_values (
 component_feature_id BIGINT UNSIGNED NOT NULL, parameter_definition_id BIGINT UNSIGNED NOT NULL,
 value_text TEXT, value_number DECIMAL(14,4), value_boolean BOOLEAN,
 PRIMARY KEY(component_feature_id,parameter_definition_id),
 CHECK((value_text IS NOT NULL) + (value_number IS NOT NULL) + (value_boolean IS NOT NULL) = 1),
 FOREIGN KEY(component_feature_id) REFERENCES component_features(id),
 FOREIGN KEY(parameter_definition_id) REFERENCES feature_parameter_definitions(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE task_item_features (
 task_id BIGINT UNSIGNED NOT NULL, item_feature_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(task_id,item_feature_id), FOREIGN KEY(task_id) REFERENCES production_tasks(id),
 FOREIGN KEY(item_feature_id) REFERENCES item_features(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
CREATE TABLE task_component_features (
 task_id BIGINT UNSIGNED NOT NULL, component_feature_id BIGINT UNSIGNED NOT NULL,
 PRIMARY KEY(task_id,component_feature_id), FOREIGN KEY(task_id) REFERENCES production_tasks(id),
 FOREIGN KEY(component_feature_id) REFERENCES component_features(id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
-- Zależności zadań muszą tworzyć graf bez cykli; kontroluje API.
-- Definicja parametru musi należeć do typu cechy, a typ wartości odpowiadać definicji.
-- Zakres cechy i zgodność powiązanego zadania z produktem kontroluje API.

-- Rejestr migracji produkcyjnych. Nie edytować zastosowanych migracji.
CREATE TABLE schema_migrations (
    version INT UNSIGNED PRIMARY KEY,
    release_version VARCHAR(30) NOT NULL,
    name VARCHAR(150) NOT NULL,
    checksum CHAR(64) NOT NULL,
    status ENUM('running', 'applied', 'failed') NOT NULL,
    started_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    applied_at DATETIME NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;

-- Audyt potwierdzonych zmian; zachowuje specyfikację i plan sprzed edycji.
CREATE TABLE order_change_history (
 id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
 order_id BIGINT UNSIGNED NOT NULL,
 changed_by BIGINT UNSIGNED NOT NULL,
 changed_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
 changes JSON NOT NULL,
 previous_snapshot JSON NOT NULL,
 accepted_specification JSON NOT NULL,
 FOREIGN KEY(order_id) REFERENCES orders(id),
 FOREIGN KEY(changed_by) REFERENCES users(id),
 INDEX idx_order_changes(order_id,id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
