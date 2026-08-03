#!/usr/bin/env bash
# Chequeo rapido de salud del sitio TransporteTito.
# Distingue si el problema es del edge del hosting o de la app.
set -u

URL="https://transportetito.5amsoftware.com.ar"
HOST="transportetito.5amsoftware.com.ar"

echo "== Chequeo de salud =="
echo

# 1) Interna: caddy -> nginx (sin pasar por el edge del hosting)
if docker compose exec -T caddy sh -lc "wget -qO- --header='Host: ${HOST}' http://nginx/ -T5 >/dev/null 2>&1"; then
    INTERNA="OK (app responde)"
else
    INTERNA="FALLO (app caida)"
fi

# 2) Externa: a traves del edge del hosting
EXTERNA=$(curl -sk -o /dev/null -w '%{http_code}' "${URL}/" 2>/dev/null)
LOGIN=$(curl -sk -o /dev/null -w '%{http_code}' "${URL}/login" 2>/dev/null)

echo "INTERNA   (app directa)  : ${INTERNA}"
echo "EXTERNA /        : ${EXTERNA}   (200 = ok)"
echo "EXTERNA /login   : ${LOGIN}   (200 = ok)"
echo

# 3) Verdict
if [ "${EXTERNA}" = "200" ] && [ "${LOGIN}" = "200" ]; then
    echo "VEREDICTO: TODO OK. El sitio responde bien por fuera."
elif [ "${INTERNA}" = "OK (app responde)" ] && [ "${EXTERNA}" != "200" ]; then
    echo "VEREDICTO: LA APP ESTA OK, pero el EDGE DEL HOSTING devuelve ${EXTERNA}."
    echo "           Reclamar a soporte de HostMar (el problema no es nuestro)."
else
    echo "VEREDICTO: REVISAR - la app no responde internamente o algo fallo."
    echo "           Correr: docker compose ps"
fi
