# How to set up a provider

Here are some tips on how to add and configure AI providers. For now, only Ollama is included, but eventually most providers will be documented.

## Ollama provider

First, install the "Ollama Provider" module under Extend, or with Drush using drush in provider_ollama and then configure it.

### Firewall

In Debian and Ubuntu, you can open port 11434 for Ollama in ufw, using Gufw:

1. Open "Report"
2. Select "ollama"
3. Click "+" to create rule
4. Select "Policy: Allow" and "Direction: Both"

... which creates this rule:

```php
$ sudo iptables -S
[...]
-A ufw-user-input -p tcp -m tcp --dport 11434 -j ACCEPT
-A ufw-user-output -p tcp -m tcp --dport 11434 -j ACCEPT
```

### Serve Ollama at 0.0.0.0

Stop Ollama and serve it via the 0.0.0.0 IP (from Ollama FAQ). You can check before with netstat (install net-tools)  and then  check Ollama IP's:

```php
$ sudo systemctl stop ollama
$ OLLAMA_HOST=0.0.0.0 ollama serve
$ sudo netstat -tunlp | grep 11434
tcp6       0      0 :::11434                :::*                    LISTEN      11027/ollama
$ curl http://127.0.0.1:11434
Ollama is running
$ curl http://0.0.0.0:11434
Ollama is running

```

Open a new terminal window, start Ollama, using dolphin-llama3 model in this example:

```php
$ ollama run dolphin-llama3
pulling manifest
pulling ea025c107c1c... 100% ▕███████████████████████████████████████████████████████▏ 4.7 GB
[...]
success
>>> Send a message (/? for help)
```

Open another terminal window, and verify that it is running

```php
$ ollama ps
NAME                    ID             SIZE     PROCESSOR         UNTIL
dolphin-llama3:latest   613f068e29f8   5.4 GB   71%/29% CPU/GPU   1 minute from now
```

### Verify host.docker.internal in DDEV

If you are using DDEV, you can check inside DDEV that everything works:

```php
$ ddev ssh
$ ping host.docker.internal
PING host.docker.internal (172.17.0.1) 56(84) bytes of data.
64 bytes from host.docker.internal (172.17.0.1): icmp_seq=1 ttl=64 time=0.123 ms
[...]
$ curl host.docker.internal:11434
Ollama is running
```

... and that the model is available:

```php
$ curl host.docker.internal:11434/api/tags
{
  "models": [
  {
    "name": "dolphin-llama3:latest",
    "model": "dolphin-llama3:latest",
[...]
```

### Ollama Authentication

Add these values under /admin/config/ai/providers/ollama:

Host Name: http://host.docker.internal
Port: 11434

### AI Chat explorer

You can try the provider with the AI Chat Explorer at /admin/config/ai/development/chat-generation:

1. In the right hand side under "LLM Provider", select "Ollama"
2. Under "Model", select "dolphin-llama3:latest"
3. Enter a prompt in "Message" in the left side and click "Ask the AI"

If all went well, you should receive a reply.
