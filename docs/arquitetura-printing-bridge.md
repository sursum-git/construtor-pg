# Arquitetura da bridge interna de impressao

Esta frente padroniza a emissao de artefatos do produto sem trocar os endpoints atuais e sem introduzir transporte fisico agora.

## Separacao em 3 camadas

1. **Geracao de artefato**
   - gera `pdf`, `html`, `csv` e `xlsx`;
   - hoje atende `reports`, `special_document` e `regulated_document`;
   - roda no backend, sem depender de impressora real.

2. **Entrega ao cliente**
   - entrega o artefato por `download`, `browser_inline` ou `archive`;
   - hoje a implementacao ativa e `download`;
   - o payload externo continua em `fileName`, `contentType`, `contentBase64`, `format`.

3. **Transporte para impressora real**
   - reservado para fases futuras;
   - contratos previstos para `raw_tcp_9100`, `cups`, `qz_tray` e `file_spool`;
   - ainda sem implementacao funcional.

## Como isso se encaixa no produto

- `reports`
  - continua como trilha de relatorios operacionais e analiticos;
  - usa a bridge para gerar/exportar artefatos.

- `special_document`
  - continua como trilha de documento interno controlado;
  - usa a bridge para gerar/exportar `html` e `pdf`.

- `regulated_document`
  - continua como trilha com `prepare`, `render`, `issue`, `verify` e `artifact`;
  - usa a bridge para gerar o artefato emitido antes de armazenar/entregar.

## Contratos internos

A bridge interna fica em `backend/src/Printing/` e hoje expoe:

- contratos:
  - `ReportGeneratorInterface`
  - `DocumentArtifactGeneratorInterface`
  - `PrinterTransportInterface`
  - `LabelBuilderInterface`
  - `ReceiptBuilderInterface`
  - `TemplateRendererInterface`
- DTOs:
  - `ReportRequest`
  - `ReportResult`
  - `DocumentArtifactRequest`
  - `PrintJob`
  - `PrintResult`
  - `PrinterConfig`
- enums:
  - `ContentType`
  - `PrintStatus`
  - `PrinterLanguage`
- exceptions:
  - `PrintingException`
  - `PrinterConnectionException`
  - `MissingDependencyException`
  - `InvalidTemplateException`
  - `UnsupportedPrinterLanguageException`

## Limite desta fase

- nao existe ainda `ESC/POS`;
- nao existe ainda `ZPL/EPL`;
- nao existe ainda envio por `TCP 9100`, `CUPS` ou `QZ Tray`;
- qualquer tentativa de usar transporte fisico deve falhar com erro controlado.
