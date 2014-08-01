scripts
=======

after_alarm_event.php  - Script to use for Asterisk to mimic a alarm center service. This script can dail your phone, send messages (email, sms MQTT, whatsapp) for certain alarm events. This way you can avoid expensive alarm centers and save phone cost

To use this, connect your Alarm to a VOIP ATA instead of a regular phone. Run Asterisk (e.g. on simple hardware like a Raspberry PI with raspbx (http://www.raspberry-asterisk.org/) or e.g. on a Synology NAS) and configure Asterisk according the instructions. 

This version enhances the original version such that you get only the relevant messages, the messages are more descriptive and there are more output possibilities

For MQTT link this uses the phpMQTT library from here https://github.com/bluerhinos/phpMQTT
For the WhatsApp link it uses this library to send the messages https://github.com/tgalal/yowsup


Note that parallel another flavour of the script was build. You can see it here
https://github.com/pleasantone/alarmevent
Here you can also find some additional information on how to setup Asterisk
