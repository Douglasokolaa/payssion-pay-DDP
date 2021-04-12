# payssion-pay-DDP

## Installation
- Download this repository
- In project root, Navigate to `gateways/`
- Upload the file in to this directory and extract, you should see the folder `passion`
- I.e you now have `gateways/payssion/*`

After Uploading the file you need to run the following query on your database, 
prefarably using PHPMyAdmin.
- In PHPMyAdmin navigate to your table, Copy and paste the sql below then run.
```sql
INSERT INTO `gateways` (`id`, `name`, `displayname`, `dir`, `live`, `extra_txt`, `extra_txt2`, `extra_txt3`, `extra`, `extra2`, `extra3`, `is_recurring`, `active`) VALUES (NULL, 'payssion', 'Payssion', 'payssion', '0', 'API Key', 'Secret Key', 'Currency Code\r\n', '', '', 'USD', '0', '0')
```
