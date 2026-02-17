# The Buwana Authentication Protocol

The Buwana system is inspired by the challenges we've had over the years developing and operating a social action platform-- while maintaining our regenerative principles.  After using with great reluctance corporate account authentication protocols, it is clear that moving forward, we need our own account system for login access to our websites and apps so that we're not dependent on for-profit services.

By casting aside the proposition of a fast and easy solution for a limited, close-source corporate authentication, we can instead we can provide a compelling online account system that fulfills our values, principles and needs-- and provide the service for other resonant organizations, movements and companies.

Much as Google, Apple, and Facebook logins-- Buwana accounts can provide a user account with core privacy and security and access methods to access GoBrik, Ecobricks.org, Open Books, Brikcoin wallet, plastic and impact accounting, and more GEA functionality.

Our vision is that this account authentication system will be useable by other resonant regenerative apps as a credential management system.

Our vision is that Buwana accounts will empower regenerative apps through their shared data-resonance.  A Buwana account will hold core community, bioregion and earthcycle data scopes that unite regenerative users and will enable these scopes to be transfereable between apps.  A user of our GoBrik app can log their ecobricks to their community, while at the same time scheduling cyclic events for this same community on Earthcal!  The Buwana system will be able to prioritize resonance, privacy and security precisely because it is presided over by a not-for-profit, for-earth enterprise (the Global Ecobrick Alliance: https://ecobricks.org/en/about.php).

Buwana accounts are stored and managed in a database separate from our main GoBrik.com and Ecobricks.org services.  

#### Where does the term 'Buwana' come from?

The word "bhuwana" (also spelled "buwana" or "bhuana") in Indonesian and other regional languages such as Balinese and Javanese also means "world" or "universe." Like "bumi," "bhuwana" has its roots in Sanskrit. The Sanskrit word "bhūvana" (भुवन) means "world," "earth," or "universe."

The connection between "bumi" and "bhuwana" lies in their shared Sanskrit origin and their similar meanings related to the concept of the world or earth. While "bumi" directly derives from "bhūmi," meaning earth or ground, "bhuwana" comes from "bhūvana," which refers to the world or universe in a broader sense. Both terms reflect the deep influence of Sanskrit on the Indonesian language and its regional variants.

## Installing Dependencies

This project uses Composer for PHP package management. After cloning the repository, run:

```
composer install
```

This command creates the `vendor/` directory with all required libraries.

## Running Tests

PHPUnit is used for unit testing. After installing dependencies with Composer, run:

```
vendor/bin/phpunit
```

The configuration file `phpunit.xml` is provided in the repository.

## Helper Functions

`check_user_app_connection()` verifies that a logged-in user has an active
connection to the requesting application. If no connection record exists the
function now redirects using an absolute path (`/$lang/app-connect.php`) so that
the redirect works even when called from nested scripts.


# The Buwana Authentication Protocol

Buwana is an open authentication and credential system designed for regenerative platforms.

The Buwana system emerged from years of building and operating social action platforms while maintaining regenerative principles. After reluctantly relying on corporate authentication services, it became clear that we needed our own account infrastructure—one that is not dependent on for-profit platforms and that aligns with ecological and social values.

Rather than choosing a fast, closed-source corporate solution, Buwana provides an open, value-aligned authentication protocol that can serve both our own ecosystem and other resonant organizations.

Much like Google, Apple, or Facebook login systems, Buwana accounts provide secure identity and access across multiple applications—while prioritizing privacy, interoperability, and ecological ethics.

Buwana enables account access to:

- GoBrik  
- Ecobricks.org  
- Open Books  
- Brikcoin wallets  
- Plastic and impact accounting  
- EarthCycle and community tools  

Our long-term vision is for Buwana to serve as a credential management system for regenerative apps worldwide.

---

## Shared Regenerative Identity

Buwana accounts are designed to empower regenerative applications through shared data resonance.

Each account holds core scopes such as:

- Community affiliation  
- Bioregional context  
- EarthCycle timing  

These scopes are transferable between apps. For example, a GoBrik user can log ecobricks for their community while simultaneously scheduling cyclical events for that same community on EarthCal.

Because Buwana is stewarded by the Global Ecobrick Alliance, a not-for-profit Earth Enterprise, it can prioritize resonance, privacy, and security over monetization or surveillance.

Buwana accounts are stored and managed in a database separate from GoBrik and Ecobricks.org services.

---

## Architecture Overview

Buwana is designed as a centralized identity provider (IdP) with a separated authentication database and API layer.

Core architectural components:

- Identity Database — Stores user credentials, hashed passwords, and core identity metadata.
- OAuth-like Token System — Issues signed tokens for authenticated sessions.
- App Connection Layer — Manages authorized connections between user accounts and client applications.
- Scope Management Engine — Controls which data scopes an application may access.
- API Gateway — Validates tokens and enforces permissions before granting access.

Security principles:

- Passwords are hashed and salted.
- Authentication database is isolated from application databases.
- Applications never directly access credential storage.
- Tokens are short-lived and verifiable.
- Access is explicitly scoped per application.

This separation ensures that even if one application is compromised, credential integrity remains protected.

---

## Scopes & Permissions

Buwana uses a scoped permission model to grant applications access only to the minimum data required.

Example scopes:

- `profile.basic` — Access to display name and public profile data.
- `community.read` — View community affiliation.
- `community.write` — Modify community data (restricted).
- `ecobricks.log` — Submit ecobrick log entries.
- `earthcycle.read` — Access cyclical calendar data.
- `wallet.read` — View Brikcoin balance.
- `wallet.write` — Submit wallet transactions (restricted).

Scopes are:

- Explicitly requested by applications.
- Explicitly granted by users.
- Stored in connection records.
- Enforced at the API layer.

This model ensures interoperability without surrendering privacy or control.

---

## Project Stewardship

The Buwana Authentication Protocol is developed, maintained, and governed by the Global Ecobrick Alliance.

The Alliance is a not-for-profit Earth Enterprise operating under regenerative principles, dedicated to supporting the global plastic transition movement through open technologies, education, and ecological infrastructure.

Buwana is part of the Alliance’s broader ecosystem alongside GoBrik and Ecobricks.org, providing the shared identity layer that connects regenerative tools, communities, and data.

---

## Where does the term “Buwana” come from?

The word *bhuwana* (also spelled *buwana* or *bhuana*) in Indonesian and related languages such as Balinese and Javanese means “world” or “universe.”

It derives from the Sanskrit *bhūvana* (भुवन), meaning “world,” “earth,” or “universe.”

This is closely related to *bumi* (from Sanskrit *bhūmi*, meaning ground or earth). Both terms reflect the deep influence of Sanskrit on Indonesian language and culture and express a holistic understanding of planetary existence.

---

## Installing Dependencies

This project uses Composer for PHP package management. After cloning the repository, run:

```
composer install
```

This command creates the `vendor/` directory with all required libraries.

---

## Running Tests

PHPUnit is used for unit testing. After installing dependencies with Composer, run:

```
vendor/bin/phpunit
```

The configuration file `phpunit.xml` is provided in the repository.

---

## Helper Functions

`check_user_app_connection()` verifies that a logged-in user has an active connection to the requesting application.

If no connection record exists, the function redirects using an absolute path (`/$lang/app-connect.php`) so that redirects work correctly even when called from nested scripts.
