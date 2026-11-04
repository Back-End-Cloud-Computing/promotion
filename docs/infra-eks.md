# Infra e roteiro do dia da N2 (Amazon EKS)

Fonte: aviso do professor sobre a ordem das apresentações, colado pelo Lucas em 04/11/2026. O que não foi
rodado vem marcado como **não testado**. Nenhuma credencial aparece aqui.

## 1. Regras do dia

| Item | Valor |
|---|---|
| Data e horário | terça-feira 10/11/2026, 18h30 às 21h30 |
| Formato | demonstração prática em equipe, **5 minutos** por equipe, sem PowerPoint |
| Janela por equipe | 9 minutos (primeira 18h30, última 21h12 a 21h21) |
| Infra | provisionada, aplicação implantada e serviços operacionais **antes** do slot; não há tempo para deploy nem para corrigir infraestrutura |
| Preparação obrigatória | comandos `kubectl` prontos para Pods, Deployments, Services, réplicas, REST e RabbitMQ |
| Nota | R1 3,0 (microsserviços e diversidade) + R2 4,0 (EKS e escalabilidade) + R3 3,0 (REST e RabbitMQ) = 10,0 |
| Revisão de nota | 17/11/2026 |

Os 4 itens que o professor pede para ver: aplicação no EKS, número de réplicas com escalonamento, serviços em
linguagens diferentes e publicação e consumo no RabbitMQ.

Nosso número de equipe e o horário do slot: **a confirmar** (a lista do professor usa só o número da equipe).

## 2. Infra

| Item | Valor |
|---|---|
| Conta | Learner Lab de **um** integrante (o anfitrião); o cluster pertence a essa conta |
| Anfitrião | a definir com o grupo |
| Região | `us-east-1` |
| Cluster | `ganjj`, 2 nodes `t3.large`, criado por `eks/create-cluster.sh` do `api-gateway` |
| Aplicação | `eks/apply.sh` aplica Ingress NGINX, secrets e os 11 serviços |
| Entrada | ELB público do NGINX Ingress, regra única para o `api-gateway` |
| Imagens | públicas (Docker Hub e GHCR); o cluster não precisa de credencial de registry |
| Dados | `emptyDir` no lugar de PVC (o node role do lab não anexa EBS); recriar o pod apaga o banco |

Passo a passo, diferenças dos manifests e como apagar: [`api-gateway/eks/README.md`](https://github.com/Back-End-Cloud-Computing/api-gateway/blob/main/eks/README.md).

### Janela do lab (4 h)

A sessão do Learner Lab dura 4 h e as credenciais vencem com ela. O ELB continua com o mesmo nome enquanto o
cluster existir, mas um cluster novo gera outro hostname. Se o lab expirar durante a janela, o cluster deve ser
tratado como perdido (**não testado** o que acontece com o EKS quando a sessão acaba).

Regra de conta, com `H` = início do slot da equipe:

- iniciar o lab e criar o cluster **antes** de `H - 1h15` (criação 15 a 20 min, `apply.sh` e conferência mais ~25 min,
  mais folga);
- a sessão cobre até `início do lab + 4h`; o slot termina em `H + 9 min`, então o lab não pode ter começado antes
  de `H + 9 min - 4h`.

Exemplo: slot 19h15 a 19h24, lab iniciado entre 15h24 e 18h00. Slot 21h12: lab entre 17h21 e 19h57.

### Acesso dos outros integrantes (hipóteses, **não testadas**)

- O `kubeconfig` vale na conta do anfitrião. Quem não é anfitrião não roda `kubectl` no cluster dele.
- A API pelo ELB é pública: qualquer colega faz `curl` na URL sem conta AWS.
- Para ver o console da AWS é preciso estar cadastrado no lab; confirmar com o professor se o e-mail de cada um
  foi cadastrado.
- Opção para o dia: o anfitrião projeta o terminal e cada integrante dita ou digita o comando do próprio serviço
  nessa máquina.

## 3. Pré-voo (véspera e hora do slot)

- [ ] Credenciais do lab ativas e `aws eks list-clusters --region us-east-1` vazio antes de criar.
- [ ] `bash eks/create-cluster.sh` e `bash eks/apply.sh`; se os nodes ficarem `NotReady` com
  `cni plugin not initialized`: `aws eks create-addon --cluster-name ganjj --region us-east-1 --addon-name vpc-cni`.
- [ ] 23 pods `Running`, nenhum `ImagePullBackOff`.
- [ ] Rodar `bash scripts/demo-eks.sh` uma vez inteiro (faz o seed dos cupons, cria o produto e o pedido); o
  Mongo e o MySQL sobem vazios.
- [ ] Terminal já aberto com os comandos da seção 4 e `ELB` exportado; fonte grande.
- [ ] Tela do `kubectl get pods -A` e do `list_queues` salvas (print) como plano B.
- [ ] Depois da apresentação: apagar tudo (`eksctl delete cluster`) e conferir `aws eks list-clusters`.

## 4. Roteiro de 5 minutos

Cada integrante fala do próprio serviço enquanto o comando dele roda; o professor deve ver a participação de todos
(**pergunta em aberto**: com 5 minutos e cinco pessoas, confirmar com o professor se uma frase por pessoa basta).

| Min | Item do professor | Rubrica | Comando |
|---|---|---|---|
| 0:00 a 1:00 | Aplicação no EKS | R2 | `kubectl get nodes`; `kubectl get deploy,pods,svc` |
| 1:00 a 2:00 | Diversidade tecnológica | R1 | `kubectl get pods -o custom-columns=POD:.metadata.name,IMAGE:.spec.containers[*].image` |
| 2:00 a 3:00 | Escalabilidade horizontal | R2 | ver bloco abaixo |
| 3:00 a 5:00 | RabbitMQ e REST | R3 | `bash scripts/demo-eks.sh`, depois `list_queues` e log do consumer |

Linguagem por serviço (para falar durante o minuto 1:00): authorization e support em Java, client em Go, order em
C#, shopping-cart em Node.js, product, llm-provider, embedding-reranking e vector-db em Python, promotion em PHP,
api-gateway em TypeScript. Detalhe e bancos: [`guia-estudo-n2.md`](guia-estudo-n2.md), seção 1.

```bash
ELB=$(kubectl -n ingress-nginx get svc ingress-nginx-controller -o jsonpath='{.status.loadBalancer.ingress[0].hostname}')

# Minuto 0:00 a 1:00
kubectl get nodes
kubectl get deploy,pods,svc

# Minuto 2:00 a 3:00: réplicas, balanceamento e escalonamento
kubectl get deploy promotion api-gateway
for i in $(seq 10); do curl -s -o /dev/null -D - "http://$ELB/api/sale" | grep -i served-by; done
kubectl scale deploy/promotion --replicas=3
kubectl get pods -l app=promotion -w          # Ctrl+C quando o terceiro ficar Ready
kubectl scale deploy/promotion --replicas=2   # volta ao estado do kit

# Minuto 3:00 a 5:00: fluxo REST + mensageria
bash scripts/demo-eks.sh
kubectl exec deploy/rabbitmq -- rabbitmqctl list_queues name messages consumers
kubectl logs deploy/promotion-consumer --tail=10
```

Só `promotion` e `api-gateway` têm 2 réplicas no kit EKS (os demais ficam com 1 para caber em 2 nodes), por isso a
escala se mostra neles. O `kubectl scale` em 3 réplicas é do ensaio de hoje em diante: **não testado** no EKS.

O RabbitMQ não tem painel (a imagem `rabbitmq:4.1-alpine` do support não traz o plugin de management), então a
prova é `list_queues` com consumidor, o log do `promotion-consumer` e o `usage_count` do cupom subindo 1.

## 5. Plano B

Se o ELB não resolver ou o lab cair: mostrar as evidências gravadas em [`evidencias/`](evidencias/) (27/10, 02/11 e
03/11), os manifests e os logs, dizendo que a execução é de data anterior. A implantação no EKS é obrigatória e o
plano B perde pontos de R2.
