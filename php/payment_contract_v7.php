<?php
/** Contrato para futura integração: nenhuma cobrança acontece neste estágio. */
interface CfPaymentProviderV7 {
    /** Deve validar assinatura usando segredo do provedor e payload original. */
    public function verifyWebhook(string $rawBody,array $headers): bool;
    /** Deve retornar um ID estável de evento para idempotência. */
    public function webhookEventId(string $rawBody): string;
    /** Consulta o provedor para conciliação; não confia apenas no webhook. */
    public function reconcile(string $externalPaymentId): array;
}
function cfPaymentEventKey(string $provider,string $providerEventId): string {
    if($provider===''||$providerEventId==='')throw new InvalidArgumentException('Identificação de evento ausente');
    return hash('sha256', $provider.':'.$providerEventId);
}
