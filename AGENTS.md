## Ponto de entrada

- A documentação funcional e de regras deste modulo vive na wiki do proprio repositório e na wiki principal da API.
- Regras transversais de qualidade, modularizacao e limites de componente vivem em `https://github.com/ControleOnline/agents-mcp/blob/master/skills/shared/code-quality.md`.
- Quando houver detalhe especifico de implementacao, prefira comentar no codigo em ingles perto da regra.
- Este arquivo deve ficar curto e servir apenas como ponte para as fontes oficiais.

## Documentação (navegação humana)

| Categoria | Destino |
| --- | --- |
| Home do módulo | https://github.com/ControleOnline/api-platform-accounting/wiki |
| Wiki ponte local | [docs/wiki.md](docs/wiki.md) |

### Por categoria — fiscal / documentos eletrônicos

| Página | O que documenta |
| --- | --- |
| [CT-e — Emissão NFePHP e DACTE](https://github.com/ControleOnline/api-platform-accounting/wiki/CT-e-Emissao-NFePHP) | Fila `cte_emission`, mínimo 1 NF no emit-cte, SEFAZ, DACTE (#16 + #26) |
| [Importação NF-e XML/ZIP](docs/technical/InvoiceTax-Import-NFe.md) | invoice_tax: parse, pessoas, vínculos, isolamento multi-tenant |

### Módulos relacionados

| Módulo | Entrada |
| --- | --- |
| api-platform-people | https://github.com/ControleOnline/api-platform-people/wiki |
| api-community | https://github.com/ControleOnline/api-community/wiki |
| app-community | https://github.com/ControleOnline/app-community/wiki |
| ui-logistic | https://github.com/ControleOnline/ui-logistic/wiki |
