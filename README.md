# Catet Aja

Transformasi cara Anda mengelola keuangan dengan Catet Aja - aplikasi cerdas berbasis AI yang menjadi teman finansial terpercaya Anda. Nikmati pelacakan pemasukan/pengeluaran yang intuitif, kategorisasi otomatis canggih, dukungan multi-mata uang real-time, dan ChatBot AI yang selalu siap membantu kapan saja.

## Fitur

- 📊 **Dasbor**: Ringkasan data keuangan dengan grafik dan statistik
- 💰 **Pelacakan Pemasukan/Pengeluaran**: Catat dan kelola transaksi keuangan
- 🤖 **Kategorisasi AI**: Kategorisasi transaksi otomatis menggunakan AI
- 🌍 **Dukungan Multi-Mata Uang**: Menangani beberapa mata uang dengan kurs pertukaran


## System Requirements

- **PHP**: 8.2 or higher
- **Node.js**: 20.0 or higher
- **Composer**: Latest stable version
- **Database**: SQLite (default), MySQL, PostgreSQL, or SQL Server

## Installation

### 1. Clone the Repository

```bash
git clone <repository-url>
cd jd_051-ahmadripaldi-catet-aja
```

### 2. Install PHP Dependencies

```bash
composer install
```

### 3. Install Node.js Dependencies

```bash
npm install
```

### 4. Environment Configuration

Copy the environment file and configure your settings:

```bash
cp .env.example .env
```

### 5. Generate Application Key

```bash
php artisan key:generate
```

### 6. Database Setup

#### For SQLite (Default)

```bash
# Create the SQLite database file
touch database/database.sqlite

# Run migrations
php artisan migrate
```

### 7. Build Frontend Assets & Run Server

```bash
# For development
npm run dev

# For production
npm run build

# Run Server
php artisan serve
```

## License

This project is licensed under the MIT License - see the [LICENSE](LICENSE) file for details.
