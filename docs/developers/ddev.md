The following are some helper files for DDEV that might make local development easier to setup.

### Milvus

[Milvus](https://milvus.io/) is a vector database that can be used together with AI Search, to set it up locally use the following file.

This will expose milvus internally on http://milvus:19530 and an gui on {sitename}.ddev.site:8521.

docker-compose.milvus.yaml
```yaml
services:
  etcd:
    container_name: ddev-${DDEV_SITENAME}-etcd
    image: quay.io/coreos/etcd:v3.5.5
    environment:
      - ETCD_AUTO_COMPACTION_MODE=revision
      - ETCD_AUTO_COMPACTION_RETENTION=1000
      - ETCD_QUOTA_BACKEND_BYTES=4294967296
      - ETCD_SNAPSHOT_COUNT=50000
    volumes:
      - ./milvus/volumes/etcd:/etcd
    command: etcd -advertise-client-urls=http://127.0.0.1:2379 -listen-client-urls http://0.0.0.0:2379 --data-dir /etcd
    healthcheck:
      test: ["CMD", "etcdctl", "endpoint", "health"]
      interval: 30s
      timeout: 20s
      retries: 3
    labels:
      com.ddev.site-name: ${DDEV_SITENAME}
      com.ddev.approot: ${DDEV_APPROOT}

  minio:
    container_name: ddev-${DDEV_SITENAME}-minio
    image: minio/minio:RELEASE.2023-03-20T20-16-18Z
    environment:
      MINIO_ACCESS_KEY: minioadmin
      MINIO_SECRET_KEY: minioadmin
    expose:
      - "9001"
      - "9000"
    volumes:
      - ./milvus/volumes/minio:/minio_data
    command: minio server /minio_data --console-address ":9001"
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:9000/minio/health/live"]
      interval: 30s
      timeout: 20s
      retries: 3
    labels:
      com.ddev.site-name: ${DDEV_SITENAME}
      com.ddev.approot: ${DDEV_APPROOT}

  milvus:
    container_name: ddev-${DDEV_SITENAME}-milvus
    image: milvusdb/milvus:v2.4.1
    command: ["milvus", "run", "standalone"]
    security_opt:
    - seccomp:unconfined
    environment:
      ETCD_ENDPOINTS: etcd:2379
      MINIO_ADDRESS: minio:9000
    volumes:
      - ./milvus/volumes/milvus:/var/lib/milvus
    healthcheck:
      test: ["CMD", "curl", "-f", "http://localhost:9091/healthz"]
      interval: 30s
      start_period: 90s
      timeout: 20s
      retries: 3
    expose:
      - "19530"
      - "9091"
    depends_on:
      - "etcd"
      - "minio"
    labels:
      com.ddev.site-name: ${DDEV_SITENAME}
      com.ddev.approot: ${DDEV_APPROOT}

  attu:
    container_name: ddev-${DDEV_SITENAME}-attu
    image: zilliz/attu:v2.3.10
    expose:
      - "3000"
    environment:
      - MILVUS_URL=milvus:19530
      - VIRTUAL_HOST=${DDEV_SITENAME}.ddev.site
      - HTTP_EXPOSE=8521:3000
      - HTTPS_EXPOSE=8521:3000
      - SERVER_NAME=${DDEV_SITENAME}.ddev.site
    depends_on:
      - "milvus"
    labels:
      com.ddev.site-name: ${DDEV_SITENAME}
      com.ddev.approot: ${DDEV_APPROOT}

```

### Mockoon
[Mockoon](https://mockoon.com/) is a service that can replicate certain repos. We currently use it for kernel and browser testing to not have to pay services to test them.

There is a Mockoon file for this under tests/assets/mockoon/. If you want to run this you can use the following file.

docker-compose.mockoon.yaml
```yaml
services:
  mockoon:
    container_name: ddev-${DDEV_SITENAME}-mockoon
    image: mockoon/cli:latest
    environment:
      - HTTP_EXPOSE=3010:3010
      - HTTPS_EXPOSE=3010:3010
      - VIRTUAL_HOST=$DDEV_HOSTNAME
      - MOCKOON_BASEHOST=http://mockoon:3010
    command: [ "--data", "/data/openai.json", "--port", "3010" ]
    volumes:
      - ../web/modules/custom/ai/tests/assets/mockoon/:/data/:readonly
    labels:
      com.ddev.site-name: ${DDEV_SITENAME}

```
