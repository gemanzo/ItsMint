# ItsMint: Trading Card Game Collection & Price Tracker

ItsMint is a web application designed for enthusiasts and collectors of Trading Card Games (TCGs). It allows users to manage their card collections, track card prices, and stay updated with the latest market values. The application leverages the CardTrader API to fetch real-time pricing information and provides a user-friendly interface to browse and filter through various cards (Blueprints), their expansions, and specific conditions.

## Key Features

-   **Blueprint Management**: View and manage a comprehensive database of TCG cards (Blueprints).
-   **Price Tracking**: Automatically fetches and updates card prices from the CardTrader marketplace for different conditions (e.g., Near Mint, Played) and versions (e.g., foil).
-   **Expansion Filtering**: Browse cards by their specific expansion sets.
-   **Category Filtering**: Filter cards by categories, such as "Single Cards," to easily find specific types of items.
-   **Dynamic Dashboard**: An intuitive dashboard (built with Inertia.js and Vue.js) allows users to:
    -   View a paginated list of Blueprints.
    -   See the latest price for each Blueprint.
    -   Filter Blueprints by category (defaulting to "Single Cards") and expansion.
    -   View detailed information for each Blueprint.
-   **Background Job Processing**: Uses Laravel's queue system to efficiently update card prices in the background without impacting user experience.
-   **API Integration**: Connects with the CardTrader API to ensure up-to-date pricing and card information.
-   **Batch Operations**: Efficiently queues and processes price updates for a large number of cards in manageable batches.

## Technology Stack

-   **Backend**: Laravel (PHP)
-   **Frontend**: Vue.js with Inertia.js
-   **Database**: PostgreSQL
-   **Styling**: Tailwind CSS
-   **API Integration**: CardTrader API

## Getting Started

(To be filled in with specific setup instructions for your project, e.g., cloning, .env setup, composer install, npm install, database migrations, running the dev server)

### Prerequisites

-   PHP (specify version, e.g., 8.2+)
-   Composer
-   Node.js & npm
-   (Specify database, e.g., PostgreSQL server)
-   A CardTrader API Token (configure in `.env`)

### Installation & Setup

1.  **Clone the repository:**
    ```bash
    git clone <your-repository-url>
    cd ItsMint
    ```
2.  **Install PHP dependencies:**
    ```bash
    composer install
    ```
3.  **Install JavaScript dependencies:**
    ```bash
    npm install
    npm run dev # or build for production
    ```
4.  **Environment Configuration:**
    -   Copy `.env.example` to `.env`: `cp .env.example .env`
    -   Generate an application key: `php artisan key:generate`
    -   Configure your database credentials in `.env`.
    -   Add your CardTrader API Token to `.env`:
        ```
        CARDTRADER_API_TOKEN=your_api_token_here
        CARDTRADER_API_BASE_URL=https://api.cardtrader.com/api/v2 # or the correct base URL
        ```
5.  **Run database migrations:**
    ```bash
    php artisan migrate
    ```
6.  **(Optional) Seed the database:**
    ```bash
    php artisan db:seed # If you have seeders
    ```
7.  **Serve the application:**
    ```bash
    php artisan serve
    ```
    And visit `http://localhost:8000` (or your configured URL).

## Key Commands

-   `php artisan itsmint:queue-price-updates`: Queues jobs to update prices for all blueprints.
    -   `--blueprint_ct_id={id}`: Queue update for a specific blueprint.
    -   `--batch_size={size}`: Number of jobs per batch (default 100).
-   `php artisan queue:work --queue=price-updates`: Process the price update jobs (ensure your queue worker is running).

## Docker Environment

This project includes a Docker setup for a consistent development environment.

### Docker Commands

Refer to `docker/README.MD` for instructions on initializing and managing the Docker environment. Key scripts include:

-   `init-docker.sh`: Sets up and starts the Docker containers.

---

This README provides a general overview. You can expand sections like "Getting Started" with more specific details relevant to your project's setup and deployment process.
