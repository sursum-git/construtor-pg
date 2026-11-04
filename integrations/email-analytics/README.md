# Gestão de e-mails

Defina `EMAIL_ANALYTICS_DATABASE_URL` no backend como `postgresql://USUARIO:SENHA@HOST:5432/correio`. O usuário deve ter somente `SELECT` sobre `notificacoes`.

Abra `/email-analytics.html`. A página consome os endpoints de resumo e registros.
