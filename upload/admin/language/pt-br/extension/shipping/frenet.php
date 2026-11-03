<?php
// Heading
$_['heading_title']         = 'Frenet';

// Text
$_['text_extension']        = 'Extensões';
$_['text_success']          = 'Frete Frenet modificado com sucesso!';
$_['text_edit']             = 'Editando Frenet';
$_['text_version']          = 'Módulo Frenet %s';
$_['text_migrated']         = 'A Chave e a Senha da versão anterior foram removidas: a Frenet agora autentica só pelo token.';
$_['text_token_required']   = 'Sem token, a Frenet não cota. Gere o token no painel Frenet e cole no campo Token.';
$_['text_token_saved']      = 'Token salvo. Deixe em branco para mantê-lo.';
$_['text_panel']            = 'Abrir o painel Frenet';
$_['text_adjusted']         = '%s ajustado para %d (aceita de %d a %d).';

// Entry
$_['entry_token']           = 'Token';
$_['entry_postcode']        = 'CEP de origem';
$_['entry_msg_prazo']       = 'Mensagem do prazo de entrega';
$_['entry_weight_class']    = 'Classe de peso em quilos';
$_['entry_length_class']    = 'Classe de medida em centímetros';
$_['entry_timeout']         = 'Tempo limite (segundos)';
$_['entry_attempts']        = 'Tentativas';
$_['entry_debug']           = 'Debug';
$_['entry_status']          = 'Situação';
$_['entry_sort_order']      = 'Posição';

// Help
$_['help_token']            = 'Token gerado no painel Frenet. Por segurança, o token salvo nunca é exibido.';
$_['help_msg_prazo']        = 'Nome do frete na loja: %s é o serviço e %d os dias de entrega. Em branco, mostra só o serviço.';
$_['help_weight_class']     = 'Classe de peso da loja que corresponde ao quilograma. O peso de cada produto é convertido para ela.';
$_['help_length_class']     = 'Classe de medida da loja que corresponde ao centímetro. As medidas de cada produto são convertidas para ela.';
$_['help_timeout']          = 'Quanto cada tentativa de cotação espera a Frenet responder, de 2 a 10 segundos.';
$_['help_attempts']         = 'Quantas vezes a cotação é enviada quando a Frenet não responde ou responde com erro do servidor, de 1 a 3. Token inválido e outros erros 4xx nunca são repetidos.';
$_['help_debug']            = 'Grava em frenet.log cada requisição e resposta da cotação, sem o token. Os erros são gravados sempre.';
$_['placeholder_msg_prazo'] = '%s - Em média %d dia(s)';

// Error
$_['error_permission']      = 'Atenção: você não tem permissão para modificar a extensão Frenet!';
