## Ponto de entrada

- A documentação funcional e de regras deste módulo vive na wiki do próprio repositório e na wiki principal da API.
- Regras transversais de qualidade, modularização e limites de componente vivem em `https://github.com/ControleOnline/agents-mcp/blob/master/skills/shared/code-quality.md`.
- Quando houver detalhe específico de implementação, prefira comentar no código em inglês perto da regra.
- Este arquivo deve ficar curto e servir apenas como ponte para as fontes oficiais.

## Documentação (navegação humana)

| Categoria | Destino |
| --- | --- |
| Home do módulo | https://github.com/ControleOnline/api-platform-accounting/wiki |
| Wiki ponte local | [docs/wiki.md](docs/wiki.md) |

### Por categoria — fiscal / documentos eletrônicos

| Página | O que documenta |
| --- | --- |
| [CT-e — Emissão com NFePHP e DACTE](https://github.com/ControleOnline/api-platform-accounting/wiki/CT-e-Emissao-NFePHP) | Fila `cte_emission` / Messenger `CteEmission`; mínimo 1 NF no emit-cte; Closed só após SEFAZ; DACTE (#16 + #26, app-community#689) |
| [Importação NF-e XML/ZIP](docs/technical/InvoiceTax-Import-NFe.md) | invoice_tax: parse, pessoas, vínculos, isolamento multi-tenant |

### Módulos relacionados

| Módulo | Entrada |
| --- | --- |
| api-platform-people | https://github.com/ControleOnline/api-platform-people/wiki |
| api-community | https://github.com/ControleOnline/api-community/wiki |
| app-community | https://github.com/ControleOnline/app-community/wiki |
| ui-logistic | https://github.com/ControleOnline/ui-logistic/wiki |
