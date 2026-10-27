<?php

namespace App\Console\Commands;

use App\Domain\Coupons\Exceptions\InvalidOrderConfirmedException;
use App\Domain\Coupons\Services\OrderConfirmedHandler;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Sleep;
use JsonException;
use PhpAmqpLib\Connection\AMQPStreamConnection;
use PhpAmqpLib\Message\AMQPMessage;
use PhpAmqpLib\Wire\AMQPTable;
use Throwable;

/**
 * Consumidor da fila `promotion.order-confirmed` (contrato em docs/contrato-api.md).
 *
 * Roda num Deployment próprio. Se a conexão com o broker cair, o comando
 * termina com erro e o Kubernetes reinicia o pod; a mensagem sem ack volta
 * para a fila.
 */
class ConsumeOrderConfirmed extends Command
{
    public const EXCHANGE = 'ganjj.orders';

    public const ROUTING_KEY = 'order.confirmed';

    public const QUEUE = 'promotion.order-confirmed';

    public const DEAD_LETTER_QUEUE = 'promotion.order-confirmed.dlq';

    protected $signature = 'promotion:consume-orders';

    protected $description = 'Consome OrderConfirmed do RabbitMQ e registra o uso do cupom';

    public function handle(): int
    {
        $config = config('service.rabbitmq');
        $connection = new AMQPStreamConnection(
            $config['host'], $config['port'], $config['user'], $config['password'], $config['vhost'],
        );
        $channel = $connection->channel();

        // O Promotion é dono da fila; o Order declara só a exchange.
        $channel->exchange_declare(self::EXCHANGE, 'direct', false, true, false);
        $channel->queue_declare(self::DEAD_LETTER_QUEUE, false, true, false, false);
        $channel->queue_declare(self::QUEUE, false, true, false, false, false, new AMQPTable([
            'x-dead-letter-exchange' => '',
            'x-dead-letter-routing-key' => self::DEAD_LETTER_QUEUE,
        ]));
        $channel->queue_bind(self::QUEUE, self::EXCHANGE, self::ROUTING_KEY);
        $channel->basic_qos(0, 1, false);
        $channel->basic_consume(self::QUEUE, '', false, false, false, false, $this->process(...));

        $this->info('Aguardando mensagens em '.self::QUEUE);
        $channel->consume();

        return self::SUCCESS;
    }

    /**
     * Ack só depois do commit. Payload inválido vai para a DLQ (reject sem
     * requeue); qualquer outra falha é tratada como transitória e reentregue.
     */
    public function process(AMQPMessage $message): void
    {
        try {
            $payload = json_decode($message->getBody(), true, flags: JSON_THROW_ON_ERROR);

            if (! is_array($payload)) {
                throw new InvalidOrderConfirmedException('payload não é um objeto JSON');
            }

            $outcome = app(OrderConfirmedHandler::class)->handle($payload);
            $message->ack();
            Log::info('OrderConfirmed processado', ['order_id' => $payload['order_id'], 'outcome' => $outcome]);
        } catch (JsonException|InvalidOrderConfirmedException $e) {
            $message->reject(false);
            Log::warning('OrderConfirmed inválido enviado para a DLQ', ['error' => $e->getMessage()]);
        } catch (Throwable $e) {
            // ponytail: espera fixa antes da reentrega; com o banco fora por muito tempo a mensagem
            // fica circulando a cada 1s. Backoff exponencial se isso virar ruído.
            Sleep::for(1)->second();
            $message->nack(true);
            Log::error('OrderConfirmed falhou, reentrega', ['error' => $e->getMessage()]);
        }
    }
}
