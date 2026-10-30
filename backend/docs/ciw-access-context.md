# Contexto autenticado CIW — transporte PHP

Contrato Sursum: RuntimeExternalJsonClient remove headers configurados X-Sursum-User, X-Session-Id e X-Sursum-Auth-Source sem distinguir capitalização. Sessão ativa fornece usuário, sessão e, quando válido, o código do provedor em sessionProperties.authentication.provider.

Origem aceita no header: identificador não vazio, até80caracteres ASCII alfanuméricos, ponto, underscore, dois-pontos ou hífen. Valores ausentes, de outro tipo ou inválidos não são encaminhados. Não existe fallback por username ou payload. O header não concede acesso: o wrapper CIW exige token próprio, origem autorizada privadamente, vínculo IMA ativo e permissão por operação.

Fontes generic conservam seu contrato anterior e não consultam a sessão para injetar esse contexto. Wrappers Sursum anteriores não precisam consumir o novo header. Sem mudança de login/provedor, grants, entidades ou schema nesta alteração de transporte.
