# Amazon SP-API Integrations

RESTful API integrations with **Amazon Selling Partner API (SP-API)** for multiple marketplaces.  
This project automates various operational workflows like **order downloading, inventory monitoring, settlement report generation, and requesting seller feedback**.

---

## 🚀 Features

- **Multi-Marketplace Support** → Handles multiple Amazon marketplaces.
- **Order Management** → Automates order downloading and syncing.
- **Inventory Monitoring** → Tracks inventory across marketplaces.
- **Settlement Reports** → Downloads financial settlement reports.
- **Feedback Automation** → Triggers automated seller feedback requests.
- **Secure Environment Setup** → Uses `.env` to manage API credentials.

---

## 🛠 Tech Stack

- **Language:** PHP 
- **APIs:** Amazon Selling Partner API (SP-API)
- **Authentication:** Login with Amazon (LWA)
- **Database:**  PostgreSQL 
- **Others:** cURL, RESTful architecture, JSON handling

---
📌 API Functionalities
Feature	Endpoint / Job	Description
Orders	/getOrders	Fetches latest Amazon orders
Inventory	/checkInventory	Monitors stock levels
Reports	/settlementReports	Downloads settlement reports
Feedback	/requestFeedback	Sends automated feedback requests
🔐 Security Notes

Never commit your .env file or API credentials.

Rotate credentials regularly in Amazon Developer Console.

Use HTTPS for secure communication.

📜 License

This project is private and maintained for internal use only.

👨‍💻 Author

Mohd Hussain
Full Stack Developer | API Integrations | Automation Specialist
GitHub • LinkedIn

## 📂 Project Structure

```bash
amazon-sp-api-work/
├── Sp-API_LWA_access_token_generator.php
├── amazon_inventory.php
├── Sp-api_getOrders.php
├── Sp-api_review_request.php
├── Sp-api_monthly_transactional_report.php
├── Sp-api_monthly_transaction_report_2.php
├── .gitignore
└── README.md


⚙️ Setup & Installation

1️⃣ Clone the Repository
git clone https://github.com/<your-username>/amazon-sp-api-work.git
cd amazon-sp-api-work

2️⃣ Install Dependencies

composer install


3️⃣ Configure Environment Variables

Create a .env file in the project root:

AWS_ACCESS_KEY_ID=your_access_key
AWS_SECRET_ACCESS_KEY=your_secret_key
LWA_CLIENT_ID=your_client_id
LWA_CLIENT_SECRET=your_client_secret
REFRESH_TOKEN=your_refresh_token
REGION=your_region

4️⃣ Run the Project
php Sp-API_LWA_access_token_generator.php

