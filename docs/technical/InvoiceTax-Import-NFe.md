# Importacao NF-e (XML/ZIP) para invoice_tax

Pagina tecnica do modulo `api-platform-accounting` para o fluxo entregue em https://github.com/ControleOnline/api-platform-accounting/issues/8

Espelho Git deste arquivo. Wiki do modulo: https://github.com/ControleOnline/api-platform-accounting/wiki

## Papel do modulo

- Faz: processar XML de NF-e (solto ou em ZIP), persistir InvoiceTax, resolver emitente/destinatario/transportadora (People + Document + Address), criar vinculos comerciais com a empresa da importacao e gravar company_id, client_id, provider_id, carrier_id.
- Nao faz: UI /cte (app-community + ui-logistic), emissao SEFAZ (#16), agendamento do worker no host.

Contrato e API (ROLE_HUMAN). Jornada visual de upload vive nas telas logisticas.

## Contrato

- Upload: POST /invoice_taxes/upload com ROLE_HUMAN
- Tipo de importacao: invoice_tax no iterator app.import_processor
- Worker: bin/console import:start --domain=<host>
- Entrada: XML NF-e ou ZIP com XMLs
- Idempotencia: invoice_tax.invoice_key UNIQUE; reuse na mesma empresa
- Isolamento: chave ja de outra company nao e reatribuida

## Modularizacao

- InvoiceTaxXmlParser: parse NF-e + extracao XML/ZIP em memoria
- InvoiceTaxPartyResolver: People / Document / Address / PeopleLink
- InvoiceTaxImportProcessor: process / persist / find + guard de company

Todos <= 500 linhas.

## Regras de negocio

1. Extrair emitente, destinatario e transportadora quando existirem no XML.
2. Cadastrar pessoa inexistente por CPF/CNPJ.
3. Cadastrar endereco quando houver dados suficientes.
4. ensureLink com a empresa da importacao.
5. Lookup por invoiceKey UNIQUE global nao muta FKs se invoiceTax.company for outra empresa (belongsToOtherCompany).
6. Fallback por invoiceNumber e findOneBy invoiceNumber + company.
7. Coluna invoice (XML) e obrigatoria no persist.

## Operacao

O processor so corre se o host agendar import:start. Sem cron, o item de /imports permanece open.
ZIP: extracao em memoria + basename.

## Modulos relacionados

- api-platform-people: People, Document, Address, PeopleLink
- api-community: pin do submodule accounting
- app-community /cte e ui-logistic: tela DefaultTable XML/ZIP
- Emissao CT-e: issue #16
