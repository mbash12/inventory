BEFORE DEPLOY
- MIGRATE SQL NEW FIELDS & TABLES
- COPY INVOICE DATA FROM PROJECTS TO PODEPOSIT
- IMPORT DESIGN PO
- COPY DO DATA FROM DELIVERY TO DELIVERY ITEMS


====================================================
IMPORT INVOICE DATA FROM PROJECTS TO PODEPOSIT
```sql
UPDATE po_deposits pd
SET total_price = (
    SELECT SUM(p.total_price) 
    FROM projects p 
    WHERE p.po_deposit = pd.id
),
invoice_status = (
    SELECT 
        CASE 
            WHEN COUNT(CASE WHEN p.invoice_status = 'sent' THEN 1 END) >= 
                 COUNT(CASE WHEN p.invoice_status = 'progress' THEN 1 END) 
            THEN 'sent'
            WHEN COUNT(CASE WHEN p.invoice_status = 'progress' THEN 1 END) > 0 
            THEN 'progress'
            ELSE NULL
        END
    FROM projects p 
    WHERE p.po_deposit = pd.id
),
invoices = (
    SELECT p.invoices
    FROM projects p
    WHERE p.po_deposit = pd.id
    ORDER BY 
        CASE p.invoice_status 
            WHEN 'sent' THEN 3
            WHEN 'progress' THEN 2
            ELSE 1
        END DESC
    LIMIT 1
);

```



IMPORT DO DATA FROM DELIVERY TO PROJECTS
```sql
UPDATE projects p
SET do_files = (
    SELECT CONCAT('[', GROUP_CONCAT(
        CASE 
            WHEN d.do_files IS NOT NULL THEN d.do_files
            WHEN d.do_file IS NOT NULL THEN CONCAT('["', d.do_file, '"]')
            ELSE NULL
        END
        SEPARATOR ','
    ), ']')
    FROM deliveries d
    WHERE d.project = p.id
    AND (d.do_files IS NOT NULL OR d.do_file IS NOT NULL)
);

```