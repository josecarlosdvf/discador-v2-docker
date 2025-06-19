# 📁 Setup do Repositório Remoto - Sistema Discador v2.0

## ✅ Status Atual
- ✅ Repositório Git local inicializado
- ✅ Commit inicial realizado (v2.0.0-docker-stable)
- ✅ Ambiente Docker 100% funcional
- ⏳ **PRÓXIMO PASSO: Configurar repositório remoto**

## 🚀 Opções de Repositório Remoto

### Opção 1: GitHub (Recomendado)

1. **Criar repositório no GitHub:**
   - Acesse https://github.com/new
   - Nome: `discador-v2` ou `sistema-discador-docker`
   - Descrição: "Sistema Discador v2.0 - Ambiente Docker completo com PHP, MariaDB, Redis, Asterisk WebRTC"
   - Deixe **VAZIO** (não inicialize com README, .gitignore ou LICENSE)

2. **Configurar e fazer push:**
   ```powershell
   cd "c:\Users\josec\OneDrive\Área de Trabalho\discador\admin_160625\discador_v2"
   
   # Adicionar remote do GitHub (substitua SEU_USUARIO pelo seu username)
   git remote add origin https://github.com/SEU_USUARIO/discador-v2.git
   
   # Fazer push inicial
   git push -u origin master
   
   # Fazer push das tags
   git push origin --tags
   ```

### Opção 2: GitLab

1. **Criar projeto no GitLab:**
   - Acesse https://gitlab.com/projects/new
   - Nome: `discador-v2`
   - Visibility: Private ou Public
   - Deixe vazio (sem README)

2. **Configurar e fazer push:**
   ```powershell
   cd "c:\Users\josec\OneDrive\Área de Trabalho\discador\admin_160625\discador_v2"
   
   # Adicionar remote do GitLab
   git remote add origin https://gitlab.com/SEU_USUARIO/discador-v2.git
   
   # Fazer push
   git push -u origin master
   git push origin --tags
   ```

### Opção 3: Servidor Git Próprio

```powershell
cd "c:\Users\josec\OneDrive\Área de Trabalho\discador\admin_160625\discador_v2"

# Configurar remote para servidor próprio
git remote add origin git@seu-servidor.com:repo/discador-v2.git
# ou
git remote add origin https://seu-servidor.com/git/discador-v2.git

# Fazer push
git push -u origin master
git push origin --tags
```

## 🔐 Configuração de Autenticação

### Para HTTPS (GitHub/GitLab):
```powershell
# Configurar credenciais (uma vez)
git config --global user.name "Seu Nome"
git config --global user.email "seu.email@exemplo.com"

# O Windows pedirá credenciais na primeira vez
# Use Personal Access Token ao invés de senha
```

### Para SSH:
```powershell
# Gerar chave SSH (se não tiver)
ssh-keygen -t rsa -b 4096 -C "seu.email@exemplo.com"

# Adicionar chave SSH ao ssh-agent
ssh-add ~/.ssh/id_rsa

# Copiar chave pública e adicionar no GitHub/GitLab
Get-Content ~/.ssh/id_rsa.pub | Set-Clipboard
```

## 📋 Comandos de Verificação

```powershell
cd "c:\Users\josec\OneDrive\Área de Trabalho\discador\admin_160625\discador_v2"

# Verificar remote configurado
git remote -v

# Verificar status
git status

# Verificar histórico
git log --oneline --graph

# Verificar tags
git tag -l
```

## 🎯 Resultado Esperado

Após configurar o repositório remoto:

```
$ git remote -v
origin  https://github.com/SEU_USUARIO/discador-v2.git (fetch)
origin  https://github.com/SEU_USUARIO/discador-v2.git (push)

$ git log --oneline
86a070a (HEAD -> master, origin/master) docs: Adicionado guia de setup para repositório remoto e backup
39d5d2c (tag: v2.0.0-docker-stable, origin/tag: v2.0.0-docker-stable) Initial commit: Sistema Discador v2.0 - Ambiente Docker completo e funcional
```

## 🔄 Workflow de Desenvolvimento

Após configurar o remote:

```powershell
# Trabalhar normalmente
git add .
git commit -m "feat: Nova funcionalidade"
git push origin master

# Para novas tags/releases
git tag v2.1.0
git push origin v2.1.0
```

## ⚠️ Importante

- **Backup Local**: Sempre mantemos o backup local em `discador_v2_backup_*`
- **Credenciais**: Nunca commitar senhas ou chaves no `.env` (já configurado no .gitignore)
- **Branches**: Considere usar branches para desenvolvimento (`git checkout -b feature/nova-funcionalidade`)

---
**Status**: ✅ Ambiente pronto para push remoto
**Última atualização**: $(Get-Date -Format "dd/MM/yyyy HH:mm")
