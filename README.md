# Booknetic Test Task

## License
TEMP_PUB_LICENSE_53452984187781

## Installation Guide
Watch the video below to learn how to install Booknetic:

[![Booknetic Installation Guide](https://img.youtube.com/vi/CiwybrqFaPM/0.jpg)](https://www.youtube.com/watch?v=CiwybrqFaPM)

## Bug Reproduction
Watch the video below to see how to reproduce the bug:

[![Booknetic Bug Reproduction](https://img.youtube.com/vi/SAN-M9iaW0o/0.jpg)](https://youtu.be/SAN-M9iaW0o)

## Task Description
### Problem
In the Booknetic booking panel, customers can select staff members for specific services. For example, booking John for a haircut (2 hours) on April 20th should make John unavailable for that time slot. 

The system includes a cart feature that allows customers to add multiple appointments before checkout. However, there's a bug in the system: when a customer adds an appointment to the cart and then clicks "Add New Booking" to add another appointment, the date & time selection step still shows previously selected time slots as available. This allows the same staff member to be booked for the same time slot multiple times.

### Task
Please investigate and resolve this issue in the booking system.

### Note
Once a time slot for a specific staff member has been selected and added to the cart, that time slot should no longer appear as available in the date & time selection step when adding additional bookings. The system should recognize time slots that are already in the cart as unavailable, even before checkout is completed.
