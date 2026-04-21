## Escopo
- Modulo fiscal e contabil do backend.
- Cobre impostos de fatura, impostos de pedido, NFe e download de notas.

## Quando usar
- Prompts sobre NFe, tributacao, `InvoiceTax`, `OrderInvoiceTax`, `ServiceInvoiceTax` e servicos fiscais.

## Limites
- Regras de cobranca, wallet e invoice pertencem primeiro a `financial`.
- Regras de pedido pertencem primeiro a `orders`.
- `accounting` deve cuidar da camada fiscal, nao do fluxo operacional de pagamento.
