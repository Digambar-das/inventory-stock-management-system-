<?php
require_once __DIR__.'/config.php';
function pdo(bool $withDb=true): PDO {
  static $db=null;
  if($withDb && $db instanceof PDO)return $db;
  $dsn='mysql:host='.DB_HOST.';port='.DB_PORT.($withDb?';dbname='.DB_NAME:'').';charset=utf8mb4';
  $pdo=new PDO($dsn,DB_USER,DB_PASS,[
    PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE=>PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES=>false
  ]);
  if(!$withDb){
    // Local XAMPP can create the database automatically. Railway already
    // provisions the selected database, so avoid requiring CREATE DATABASE.
    if(getenv('MYSQLHOST')===false && getenv('DATABASE_URL')===false){
      $pdo->exec("CREATE DATABASE IF NOT EXISTS `".str_replace('`','``',DB_NAME)."` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
    }
    return $pdo;
  }
  $db=$pdo; return $db;
}
function bootstrap_db(): PDO {
  pdo(false); $db=pdo(true);
  $sql=file_get_contents(__DIR__.'/../database/schema.sql');
  foreach(array_filter(array_map('trim',preg_split('/;\s*(?:\r?\n|$)/',$sql))) as $q){ if($q!=='') $db->exec($q); }
  migrate_db($db);
  seed_catalog($db);
  return $db;
}

/**
 * Bring databases created by older project builds up to the current schema
 * without deleting existing records. This prevents "Unknown column" errors
 * when an existing installation is upgraded.
 */
function migrate_db(PDO $db): void {
  $columns = [
    'users' => [
      'full_name' => 'VARCHAR(120) NOT NULL', 'email' => 'VARCHAR(190) NOT NULL',
      'password_hash' => 'VARCHAR(255) NOT NULL', 'role' => "ENUM('admin','manager') NOT NULL DEFAULT 'manager'",
      'store_id' => 'BIGINT UNSIGNED NULL', 'status' => "ENUM('active','inactive') NOT NULL DEFAULT 'active'",
      'created_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP', 'updated_at' => 'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'
    ],
    'categories' => ['name'=>'VARCHAR(120) NOT NULL','description'=>'TEXT NULL','status'=>"ENUM('active','inactive') DEFAULT 'active'",'created_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
    'stores' => ['name'=>'VARCHAR(120) NOT NULL','code'=>'VARCHAR(30) NOT NULL','address'=>'VARCHAR(255)','status'=>"ENUM('active','inactive') DEFAULT 'active'",'created_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
    'products' => ['sku'=>'VARCHAR(80) NOT NULL','name'=>'VARCHAR(180) NOT NULL','category_id'=>'BIGINT UNSIGNED NULL','description'=>'TEXT','unit_price'=>'DECIMAL(12,2) NOT NULL DEFAULT 0','cost_price'=>'DECIMAL(12,2) NOT NULL DEFAULT 0','reorder_level'=>'INT NOT NULL DEFAULT 10','status'=>"ENUM('active','inactive') DEFAULT 'active'",'created_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP','updated_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP'],
    'suppliers' => ['name'=>'VARCHAR(160) NOT NULL','email'=>'VARCHAR(190)','phone'=>'VARCHAR(40)','address'=>'VARCHAR(255)','status'=>"ENUM('active','inactive') DEFAULT 'active'",'created_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
    'stock' => ['store_id'=>'BIGINT UNSIGNED NOT NULL','product_id'=>'BIGINT UNSIGNED NOT NULL','quantity'=>'INT NOT NULL DEFAULT 0','reserved'=>'INT NOT NULL DEFAULT 0'],
    'stock_movements' => ['store_id'=>'BIGINT UNSIGNED NOT NULL','product_id'=>'BIGINT UNSIGNED NOT NULL','user_id'=>'BIGINT UNSIGNED NULL','type'=>"ENUM('in','out','adjustment','opening') NOT NULL",'quantity'=>'INT NOT NULL','reason'=>'VARCHAR(255)','created_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
    'purchases' => ['store_id'=>'BIGINT UNSIGNED NOT NULL','supplier_id'=>'BIGINT UNSIGNED NULL','requested_by'=>'BIGINT UNSIGNED NULL','status'=>"ENUM('requested','approved','received','rejected','cancelled') DEFAULT 'requested'",'total'=>'DECIMAL(12,2) DEFAULT 0','notes'=>'TEXT','created_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
    'purchase_items' => ['purchase_id'=>'BIGINT UNSIGNED NOT NULL','product_id'=>'BIGINT UNSIGNED NOT NULL','quantity'=>'INT NOT NULL','unit_cost'=>'DECIMAL(12,2) NOT NULL'],
    'orders' => ['store_id'=>'BIGINT UNSIGNED NOT NULL','customer_name'=>'VARCHAR(160)','customer_email'=>'VARCHAR(190)','status'=>"ENUM('pending','processing','ready','completed','cancelled') DEFAULT 'pending'",'total'=>'DECIMAL(12,2) DEFAULT 0','created_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
    'order_items' => ['order_id'=>'BIGINT UNSIGNED NOT NULL','product_id'=>'BIGINT UNSIGNED NOT NULL','quantity'=>'INT NOT NULL','unit_price'=>'DECIMAL(12,2) NOT NULL'],
    'returns' => ['order_id'=>'BIGINT UNSIGNED NULL','store_id'=>'BIGINT UNSIGNED NOT NULL','customer_name'=>'VARCHAR(160)','type'=>"ENUM('return','exchange') DEFAULT 'return'",'status'=>"ENUM('requested','approved','rejected','refunded','completed') DEFAULT 'requested'",'refund_amount'=>'DECIMAL(12,2) DEFAULT 0','reason'=>'TEXT','created_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
    'audit_logs' => ['user_id'=>'BIGINT UNSIGNED NULL','action'=>'VARCHAR(120) NOT NULL','entity'=>'VARCHAR(80)','entity_id'=>'BIGINT UNSIGNED NULL','details'=>'TEXT','ip_address'=>'VARCHAR(64)','created_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
    'login_events' => ['user_id'=>'BIGINT UNSIGNED NULL','email'=>'VARCHAR(190)','success'=>'TINYINT(1) NOT NULL','ip_address'=>'VARCHAR(64)','created_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
    'login_attempts' => ['email'=>'VARCHAR(190)','ip_address'=>'VARCHAR(64)','attempts'=>'INT NOT NULL DEFAULT 0','last_attempt'=>'TIMESTAMP NULL'],
    'notifications' => ['user_id'=>'BIGINT UNSIGNED NULL','title'=>'VARCHAR(180)','message'=>'TEXT','is_read'=>'TINYINT(1) DEFAULT 0','created_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP'],
    'settings' => ['site_name'=>"VARCHAR(160) NOT NULL DEFAULT 'InventoryPro'",'currency'=>"VARCHAR(10) NOT NULL DEFAULT 'INR'",'low_stock_alert'=>'TINYINT(1) NOT NULL DEFAULT 1','updated_at'=>'TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP']
  ];
  foreach ($columns as $table => $defs) {
    $exists = $db->prepare('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema=DATABASE() AND table_name=?');
    $exists->execute([$table]);
    if (!(int)$exists->fetchColumn()) continue;
    $presentStmt = $db->prepare('SELECT COUNT(*) FROM information_schema.columns WHERE table_schema=DATABASE() AND table_name=? AND column_name=?');
    foreach ($defs as $name => $definition) {
      $presentStmt->execute([$table,$name]);
      if (!(int)$presentStmt->fetchColumn()) {
        $db->exec("ALTER TABLE `".str_replace('`','``',$table)."` ADD COLUMN `".str_replace('`','``',$name)."` $definition");
      }
    }
  }
  // Required singleton settings row for installations upgraded from older builds.
  $db->exec("INSERT IGNORE INTO settings (id,site_name,currency,low_stock_alert) VALUES (1,'InventoryPro','INR',1)");
}
function seed_catalog(PDO $db):void{
  if((int)$db->query('SELECT COUNT(*) FROM categories')->fetchColumn()===0){
    $cats=['Electronics','Computer Accessories','Mobile & Tablets','Networking','Office Supplies','Furniture','Stationery','Printers & Scanners','Storage Devices','Cables & Adapters','Power & UPS','Audio','Cameras','Lighting','Security','Cleaning','Packaging','Tools','Kitchen & Pantry','Safety'];
    $s=$db->prepare('INSERT INTO categories(name,description) VALUES(?,?)'); foreach($cats as $i=>$c)$s->execute([$c,'Inventory category for '.$c]);
  }
  if((int)$db->query('SELECT COUNT(*) FROM stores')->fetchColumn()===0){$s=$db->prepare('INSERT INTO stores(name,code,address,status) VALUES(?,?,?,?)');for($i=1;$i<=4;$i++)$s->execute(['Store '.$i,'ST'.str_pad((string)$i,2,'0',STR_PAD_LEFT),'Branch '.$i,'active']);}
  if((int)$db->query('SELECT COUNT(*) FROM suppliers')->fetchColumn()===0){$s=$db->prepare('INSERT INTO suppliers(name,email,phone,address,status) VALUES(?,?,?,?,?)');for($i=1;$i<=10;$i++)$s->execute(['Supplier '.$i,'supplier'.$i.'@example.com','90000000'.str_pad((string)$i,2,'0',STR_PAD_LEFT),'Supplier address '.$i,'active']);}
  if((int)$db->query('SELECT COUNT(*) FROM products')->fetchColumn()===0){$cats=$db->query('SELECT id FROM categories ORDER BY id')->fetchAll(PDO::FETCH_COLUMN);$s=$db->prepare('INSERT INTO products(sku,name,category_id,description,unit_price,cost_price,reorder_level,status) VALUES(?,?,?,?,?,?,?,?)');for($i=1;$i<=100;$i++){ $cid=$cats[($i-1)%count($cats)];$s->execute(['SKU-'.str_pad((string)$i,4,'0',STR_PAD_LEFT),'Product '.$i,$cid,'Professional inventory item '.$i,round(100+$i*7.5,2),round(70+$i*5,2),10,'active']);}}
  $stores=$db->query('SELECT id FROM stores')->fetchAll(PDO::FETCH_COLUMN);$products=$db->query('SELECT id FROM products')->fetchAll(PDO::FETCH_COLUMN);
  foreach($stores as $sid) foreach($products as $pid){$st=$db->prepare('INSERT IGNORE INTO stock(store_id,product_id,quantity,reserved) VALUES(?,?,?,0)');$st->execute([$sid,$pid,($pid%20===0?5:50+($pid%30))]);}
}
