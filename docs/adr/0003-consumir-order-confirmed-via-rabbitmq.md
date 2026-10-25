# ADR 0003 — Consumir OrderConfirmed via RabbitMQ

Status: Aceito; implementação pendente

Data: 2026-10-25

Order e Promotion usarão uma exchange direct durável do RabbitMQ para o evento
`OrderConfirmed`. Embora o REST interno já satisfaça a rubrica, o endpoint de
consumo não é idempotente e não recupera indisponibilidade do Promotion. A fila
terá entrega pelo menos uma vez, payload versionado em inglês e idempotência do
consumidor por `order_id`. O Order publicará por outbox somente depois de receber
e persistir o cupom da cotação autoritativa do Cart.
