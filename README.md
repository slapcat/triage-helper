# Triage Helper

Triage Helper is a tool to assist with sorting and assigning support tickets in high-volume help desk environments. It determines who is available to work tickets, lists each agent's expertise, links available agents' names to their current tickets, and automates removing names of agents that are out of the office.

## Features

This tool currently supports:

* List available agents and assigned duties
* Filter agents by expertise
* Highlight agents who are on planned leave or out unexpectedly
* Time off integrated with SharePoint calendar
* Compatible with Microsoft Teams

## Screenshot

![Screenshot](https://github.com/jnabasny/triage-helper/blob/master/screenshot.png)

## How It Works

All data concerning agents' schedules and expertise are stored in `agents.db`. The main script, `index.php`, reads this CSV file and determines if an agent is either on shift now or is within 2 hours of coming on shift. 

It also checks to see if they are off by searching for a `#` in front of their name. The `#` can either be added manually or will be automatically added by `scheduler.php` if the agent has pre-planned time off.

The following additional scripts help with scheduling:

* `scheduler.php` - Resets which agents are off for the day by removing all `#` before their names. It then checks which agents are off today and places the `#` before their name so that `index.php` interprets them as out for the day.

* `parser.php` - Scans a mailbox for alerts from a SharePoint calendar. Parses the email to determine which agent it is for and the dates they have requested off. It then adds or removes these dates (depending on the request) to `agents.db`. This script currently only supports events that are added or deleted to the calendar, not changed.

## Roadmap

To be added in the future:

* Integrate with Freshdesk API (list open tickets, CSAT, etc.)
* Better database management
