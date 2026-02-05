# Champions Maung – Backend API

This repository contains the **Laravel backend API** for **Champions Maung**, a football betting platform using **Myanmar-style odds**.  
The backend serves a Flutter frontend and handles authentication, betting logic, wallet transactions, role-based access, and reporting.

---

## Overview

- Backend API for football betting system
- Token-based authentication using **Laravel Passport**
- Strict **role-based hierarchy** with parent–child relationships
- Supports **single** and **accumulator** betting
- Wallet balance management, payouts, and transaction tracking
- Reporting system for upper-level roles

---

## Technology Stack

- **Laravel**
- **Laravel Passport** (OAuth2 token authentication)
- **MySQL**
- RESTful API architecture

---

## Authentication

- Token-based authentication using **Laravel Passport**
- All protected endpoints require a valid access token
- Tokens are issued on login and used by the frontend application

---

## Role Hierarchy

Roles are structured from top to bottom as follows:

1. **SSSenior** *(system owner – only one account)*
2. **SSenior**
3. **Senior**
4. **Master**
5. **Agent**
6. **User**

### Role Rules

- Accounts can only be created by their **direct parent role**
- This structure ensures correct **commission, share, and reporting flow**
- Each role can access **only its own downline**

---

## Role Capabilities

### User
- Place bets (single & accumulator)
- View betting history
- View transaction history
- View match results
- View company announcements

### Agent and Above
- Cannot place bets
- View downline performance and activity
- Access reports for:
  - Daily
  - Weekly
  - Monthly
  - Yearly
  - Custom date range
- View betting history and transaction history of their children

#### Downline Visibility Examples

- **Agent** → Users created by that Agent
- **Master** → Agents created by Master + their Users
- **Senior / SSenior / SSSenior** → Expanded access based on hierarchy

---

## Core Features

### Betting System
- Single match betting
- Accumulator (multiple matches) betting
- Myanmar-style odds
- Automatic win/lose calculation
- Payout settlement and wallet update
- Full betting history tracking

### Wallet & Transactions
- Deposit units
- Withdraw units
- Automatic balance adjustments
- Complete transaction history
- Parent–child commission flow support

### Match Management
- Match listings
- Odds management
- Match results
- Bet settlement on result confirmation

### Reporting System
- User betting activity reports
- Turnover and win/lose summaries
- Transaction summaries
- Date-based filtering (daily to custom range)

### Announcements
- Company announcements visible to users
- Managed by upper-level roles

---

## License

Developed by **Thwin Htoo Aung**.

This project is **open source** and available for anyone who wants to learn, use, or extend it.  
Feel free to explore the codebase, collaborate, and ask questions if there is anything you do not understand about the project.
