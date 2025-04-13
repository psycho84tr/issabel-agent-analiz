# Issabel Agent Performance Analysis Module

## Overview
This module provides detailed performance analysis for call center agents in Issabel PBX systems. It was developed as an extension to the existing Call Center module in Issabel, offering more comprehensive agent performance metrics and analytics.

## Description
The Agent Performance Analysis module allows call center supervisors and administrators to monitor and evaluate agent performance with detailed metrics. It calculates efficiency based on agent session times and provides a comprehensive overview of agent activities.

## Features
- Track and analyze the number of calls answered by each agent
- Measure total call duration, average call duration, and maximum call duration
- Monitor agent session times and break periods
- Calculate efficiency percentages based on working hours (configurable 9AM-6PM standard)
- Filter agents by date range, queue, and agent number
- Export data to CSV, XLS, and PDF formats
- Multilingual support (English and Turkish)
- Ability to filter out inactive agents with no data

## Requirements
- Issabel PBX with Call Center module installed
- MySQL/MariaDB database access
- Asterisk Call Center statistics enabled

## Installation
1. Copy the `agent_analiz` directory to `/var/www/html/modules/`
2. Import the module in Issabel's Module Administrator
3. Set appropriate permissions for the module
4. Access the module through Call Center → Reports → Agent Performance Analysis

## Usage
1. Select the date range for analysis
2. Optionally filter by call type, queue or specific agent
3. Click on "Show" to generate the report
4. View detailed agent performance metrics
5. Export the data in your preferred format if needed

## Screenshots
(Screenshots will be added soon)

## License
This module is licensed under GPL v2, maintaining compatibility with Issabel's licensing.

## Author
Developed by Oktay Mert for Issabel Call Center environments.

## Support
For support or customization requests, please create an issue in this repository or contact the author directly.
