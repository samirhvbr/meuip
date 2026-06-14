# MEUIP.RS

Serviço "qual é o meu IP" da BLUE3 — mostra ao visitante o IP público, ASN,
provedor (ISP), país e região, com página web e endpoints `curl`.

> **v2.0 (2026):** reescrita de segurança e arquitetura. Veja [Mudanças](#mudanças-na-v20).

## Arquitetura

```
meuip.rs/
├── index.php            # front controller (roteamento)
├── config.php           # segredos (NÃO versionado — ver config.example.php)
├── config.example.php   # modelo de configuração
├── schema.sql           # tabela meuip_visits (auditoria)
├── src/                 # código (bloqueado para acesso web)
│   ├── bootstrap.php     # config + autoloader
│   ├── helpers.php       # escape, IP do cliente, respostas
│   ├── Database.php      # PDO único, lazy, prepared statements
│   ├── GeoLookup.php     # geo via cURL nativo + cache + timeout
│   ├── UserAgent.php     # SO/navegador
│   └── AccessLog.php     # registro de visitas (best-effort)
├── views/               # templates (bloqueado para acesso web)
│   ├── layout.php        # head + menu discreto + footer
│   ├── home.php          # página principal
│   └── doc.php           # documentação
└── assets/style.css     # tema claro corporativo BLUE3
```

## Setup

1. `cp config.example.php config.php` e preencha as credenciais (ou use as
   variáveis de ambiente `MEUIP_DB_*` / `MEUIP_IPINFO_TOKEN`).
2. Crie a tabela de visitas: `mysql -h <host> -u <user> -p <db> < meuip.rs/schema.sql`.
3. Garanta que o diretório de cache (`cache_dir` ou `sys_get_temp_dir()`) seja
   gravável pelo usuário do PHP-FPM.

## Endpoints

| Rota | Resposta |
|------|----------|
| `/` | IP em texto (curl) ou página HTML (navegador) |
| `/ip` | IP |
| `/asn` | ASN |
| `/isp` | Provedor |
| `/country` | País (código) |
| `/region` | Região |
| `/coord` | `lat,long` |
| `/all` | JSON com tudo |
| `/full` | Página HTML completa |
| `/doc` | Documentação |

## Auditoria (INTRANET)

As visitas são gravadas em `meuip_visits`. O painel de auditoria fica no admin
da INTRANET (`adm.blue3.com.br/admin` → card **Auditoria meuip.rs**), que lê a
tabela via conexão read-only `meuip` (padrão das conexões auxiliares). Não há
API de ingestão — a leitura é direta no banco.

## Mudanças na v2.0

- **SQL injection** eliminado — todo acesso ao banco usa prepared statements.
- **Credenciais e token** movidos para `config.php` (gitignored) + `.gitignore`.
- **XSS** corrigido — toda saída HTML passa por `htmlspecialchars`.
- **shell_exec("curl ...")** substituído por cURL nativo com timeout e cache.
- **Parsing** de ASN/geo agora via JSON (`json_decode`), não offsets de caractere.
- **Logging** por `error_log`/banco — sem `shell_exec`. Cookies não são mais gravados.
- Front controller real; endpoints `/ip`, `/asn`, etc. implementados.
- Arquivos mortos removidos (`Bindex.php`, `full.php`, `meu.php`, `ip.php`, stubs).
