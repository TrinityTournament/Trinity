#!/usr/bin/env python3
# ═══════════════════════════════════════════════════════════════════
#  TRINITY — Generador del seed (datos de prueba)
#
#  Uso (desde la raíz del proyecto):
#      python3 tools/generar-seed.py
#  Escribe database/extras/seed.sql.
#
#  NO hace falta correrlo para levantar el sistema: el SQL generado ya
#  se entrega. Está acá para poder regenerarlo / ajustarlo, y como
#  documentación de cómo se armaron los datos.
#
#  Es DETERMINÍSTICO (semilla fija): siempre produce el mismo archivo.
#
#  Qué garantiza (se verifica con database/extras/seed_verificar.sql):
#   · 50+ filas en cada tabla, respetando FK / UNIQUE / ENUM.
#   · Los partidos de torneos 'en_curso' y 'finalizado' son los que
#     generaría MatchService (mismos algoritmos: eliminación con byes
#     repartidos, liga por método del círculo, suizo ronda a ronda) y
#     avanzan como lo haría recordResult().
#   · usuarios.torneos_jugados / torneos_ganados salen de los torneos
#     finalizados sembrados (así el ranking es coherente con los datos).
#   · Las fechas son RELATIVAS al día de la carga (CURDATE()/NOW()), así
#     los torneos "abiertos" siempre tienen fecha de inicio futura.
#   · Todo lo sembrado se puede reconocer y re-sembrar: emails
#     @trinity.test y teléfonos +598 90 ... (ver bloque DELETE inicial).
# ═══════════════════════════════════════════════════════════════════
import hashlib
import json
import math
import os
import random
import unicodedata
from collections import defaultdict

RAIZ = os.path.dirname(os.path.dirname(os.path.abspath(__file__)))
SALIDA = os.path.join(RAIZ, 'database', 'extras', 'seed.sql')

rng = random.Random(20261023)

# Hashes bcrypt generados con password_hash($pw, PASSWORD_BCRYPT) de PHP
# (mismo algoritmo que AuthService). Las contraseñas están en el README.
H_ADMIN = '$2y$10$lUJiJIKFwhiw0eY2nybomOaST55DHxof3HNwSmT9NOwvoLRc8sqq2'
H_ORG   = '$2y$10$VrZ2hjQ4bA8HucuME2fB5uEd0ajm1.VWA0Z.ERUGFvZCYfOHVgyVq'
H_PART  = '$2y$10$.eNtL1I.71bLuF.x9UlSCekwvG1EUufeyvfWpRnYN3sCMDaEFqxZK'
H_DEMO  = '$2y$10$mFPAolpgO/yXZI1ipK21fuXR2J7GpeQMPS6DF0u062/F97R.ns1iG'

# Catálogos válidos (ProfileService / formulario de crear torneo)
JUEGOS = ['Brawl Stars', 'Clash Royale', 'Fortnite', 'Free Fire', 'Minecraft']
DISCIPLINAS = ['Fútbol'] + JUEGOS
PRONOUNS = ['He/him', 'She/her', 'He/they', 'She/they', 'They/them', 'Any']
ROLES_FUTBOL = ['Arquero', 'Defensor', 'Mediocampista', 'Delantero']
ESTILOS_MC = ['Defensa', 'Estratega', 'Agresivo', 'Sigilo']
ESPECIALIDADES_MC = ['Redstone', 'Minería', 'Construcción', 'PvP', 'Exploración', 'Agricultura']
MODOS_MC = ['Skyblock', 'Bedwars', 'Lucky Blocks', 'Survival', 'Creativo', 'SMP', 'Parkour']
CLUBES = ['Peñarol', 'Nacional', 'Danubio', 'Defensor Sporting', 'Wanderers', 'Liverpool',
          'Cerro', 'River Plate', 'Racing', 'Fénix', 'Progreso', 'Boston River']


# ───────────────────────── helpers SQL ─────────────────────────
class Raw(str):
    """Fragmento SQL que se emite tal cual (sin comillas)."""


def lit(v):
    if v is None:
        return 'NULL'
    if isinstance(v, Raw):
        return str(v)
    if isinstance(v, bool):
        return '1' if v else '0'
    if isinstance(v, (int, float)):
        return str(v)
    s = str(v).replace('\\', '\\\\').replace("'", "''")
    return "'" + s + "'"


def ts(off_days, minutos=0):
    """TIMESTAMP relativo a ahora: ts(-3, 90) = hace 3 días, +90 min."""
    return Raw(f"(NOW() + INTERVAL {off_days} DAY + INTERVAL {minutos} MINUTE)")


def fecha(off_days):
    return Raw(f"(CURDATE() + INTERVAL {off_days} DAY)")


def j(obj):
    return json.dumps(obj, ensure_ascii=False)


SQL = []


def insertar(tabla, cols, filas, lote=40):
    for i in range(0, len(filas), lote):
        bloque = filas[i:i + lote]
        SQL.append(f"INSERT INTO {tabla} ({', '.join(cols)}) VALUES")
        SQL.append(',\n'.join('  (' + ', '.join(lit(x) for x in f) + ')' for f in bloque) + ';')
    SQL.append('')


def sin_acentos(s):
    return ''.join(c for c in unicodedata.normalize('NFD', s) if unicodedata.category(c) != 'Mn')


# ───────────────────────── USUARIOS ─────────────────────────
M = ['Santiago', 'Matías', 'Agustín', 'Facundo', 'Nicolás', 'Joaquín', 'Federico', 'Martín', 'Gonzalo',
     'Sebastián', 'Diego', 'Maximiliano', 'Rodrigo', 'Ignacio', 'Emiliano', 'Lucas', 'Bruno', 'Franco',
     'Thiago', 'Mateo', 'Bautista', 'Felipe', 'Ramiro', 'Tomás', 'Leandro', 'Gastón', 'Andrés', 'Julián',
     'Alan', 'Damián']
F = ['Valentina', 'Camila', 'Sofía', 'Lucía', 'Martina', 'Agustina', 'Florencia', 'Micaela', 'Antonella',
     'Carolina', 'Victoria', 'Julieta', 'Catalina', 'Paula', 'Romina', 'Belén', 'Milagros', 'Abril',
     'Renata', 'Jimena', 'Daniela', 'Noelia', 'Candela', 'Rocío', 'Pilar', 'Marina', 'Luciana',
     'Natalia', 'Bianca', 'Ailén']
S = ['González', 'Rodríguez', 'Fernández', 'Pereira', 'Silva', 'Techera', 'Núñez', 'Píriz', 'Cabrera',
     'Olivera', 'Suárez', 'Acosta', 'Bentancor', 'Ferreira', 'Machado', 'Rivero', 'Sosa', 'Viera',
     'Castro', 'Rocha', 'Barreto', 'Latorre', 'Cardozo', 'Benítez', 'Correa', 'Amaral', 'Medina',
     'Ramos', 'Giménez', 'Álvarez', 'Morales', 'Pintos', 'Vázquez', 'Cáceres', 'Duarte', 'Lemos',
     'Trinidad', 'Bermúdez', 'Echeverría', 'Cuello', 'Burgos', 'Larrosa', 'Texeira', 'Ibarra',
     'Moreira', 'Maidana', 'Lacuesta', 'Sena', 'Haedo', 'Corbo']
TAGS = ['NightFury', 'LuciaPro', 'FacuGamer', 'Kratos', 'ElTano', 'Pixelada', 'Tormenta', 'Charrua',
        'MateAmargo', 'ChivitoPro', 'RamblaKing', 'ElChamuyo', 'Cuervo', 'Garra', 'Zorro']

N_USUARIOS = 64
ID_ADMIN_DEMO, ID_ORG_DEMO, ID_PART_DEMO = 1, 4, 12
ADMINS = [1, 2, 3]
ORGANIZADORES = list(range(4, 12))          # 4..11
PARTICIPANTES = list(range(12, 65))         # 12..64
INCOMPLETOS = [61, 62, 63, 64]              # registrados, perfil sin completar (tipo NULL)
JUGADORES = [u for u in PARTICIPANTES if u not in INCOMPLETOS]   # 12..60 (49)

combos = [(a, b) for a in (M + F) for b in S]
rng.shuffle(combos)
nombres_reales = []
veces_nombre, veces_apellido = defaultdict(int), defaultdict(int)
for a, b in combos:
    if veces_nombre[a] >= 2 or veces_apellido[b] >= 2:
        continue                   # variedad: ningún nombre ni apellido más de 2 veces
    veces_nombre[a] += 1
    veces_apellido[b] += 1
    nombres_reales.append((a, b))
    if len(nombres_reales) == N_USUARIOS - 3:
        break
assert len(nombres_reales) == N_USUARIOS - 3

U = {}
usados_usuario, usados_email, usados_tel = set(), set(), set()
tags = TAGS[:]
rng.shuffle(tags)


def nuevo_usuario_unico(base):
    u = base
    k = 2
    while u in usados_usuario:
        u = f'{base}{k}'
        k += 1
    usados_usuario.add(u)
    return u


idx_real = 0
for uid in range(1, N_USUARIOS + 1):
    if uid == ID_ADMIN_DEMO:
        nombre, usuario, pw = 'Admin Trinity', 'admin', H_ADMIN
        email = 'admin@trinity.test'
    elif uid == ID_ORG_DEMO:
        nombre, usuario, pw = 'Organizador Demo', 'organizador', H_ORG
        email = 'organizador@trinity.test'
    elif uid == ID_PART_DEMO:
        nombre, usuario, pw = 'Participante Demo', 'participante', H_PART
        email = 'participante@trinity.test'
    else:
        a, b = nombres_reales[idx_real]
        idx_real += 1
        nombre = f'{a} {b}'
        if uid in PARTICIPANTES and tags and rng.random() < 0.45:
            usuario = tags.pop()
            usuario = usuario + str(rng.randint(1, 99)) if rng.random() < 0.5 else usuario
        else:
            usuario = sin_acentos(a).lower()[:6] + sin_acentos(b).lower()[:3] + str(rng.randint(1, 99))
        pw = H_DEMO
        email = f"{sin_acentos(a).lower()}.{sin_acentos(b).lower().replace(' ', '')}@trinity.test"
    usuario = nuevo_usuario_unico(usuario)
    if email in usados_email:
        email = email.replace('@', f'{uid}@')
    usados_email.add(email)

    while True:
        tel = f'+598 90 {rng.randint(0, 999):03d} {rng.randint(0, 999):03d}'
        if tel not in usados_tel:
            usados_tel.add(tel)
            break

    demo = uid in (ID_ADMIN_DEMO, ID_ORG_DEMO, ID_PART_DEMO)
    modo = 'ambos' if demo else rng.choices(['ambos', 'email', 'tel'], [70, 15, 15])[0]
    U[uid] = dict(
        id=uid, nombre=nombre, usuario=usuario, password=pw,
        email=None if modo == 'tel' else email,
        telefono=None if modo == 'email' else tel,
        rol='admin' if uid in ADMINS else 'organizador' if uid in ORGANIZADORES else 'participante',
        skill=rng.gauss(0, 1), jugados=0, ganados=0,
        fav_futbol=False, fav_juegos=[],
    )

# Favoritos: 28 futboleros y ~20 jugadores por videojuego, entre los que completaron el perfil
futboleros = set(rng.sample(JUGADORES, 28)) | set(rng.sample(ORGANIZADORES + ADMINS, 4))
for g in JUEGOS:
    for uid in rng.sample(JUGADORES, 20):
        U[uid]['fav_juegos'].append(g)
for uid in ORGANIZADORES + ADMINS:
    if rng.random() < 0.5:
        U[uid]['fav_juegos'] = rng.sample(JUEGOS, rng.randint(1, 2))
for uid in futboleros:
    U[uid]['fav_futbol'] = True
for uid in JUGADORES + ORGANIZADORES + ADMINS:
    if not U[uid]['fav_futbol'] and not U[uid]['fav_juegos']:
        U[uid]['fav_juegos'] = [rng.choice(JUEGOS)]
    U[uid]['fav_juegos'] = [g for g in JUEGOS if g in U[uid]['fav_juegos']]   # orden canónico


def pool(disciplina, excluir=()):
    if disciplina == 'Fútbol':
        return [u for u in JUGADORES if U[u]['fav_futbol'] and u not in excluir]
    return [u for u in JUGADORES if disciplina in U[u]['fav_juegos'] and u not in excluir]


# ───────────────────────── TORNEOS ─────────────────────────
TITULOS = {
    'Fútbol': ['Copa Trinity de Fútbol 5', 'Liga Barrial Primavera 2026', 'Clásico Interliceos UTU',
               'Copa ITS Arias-Balparda', 'Torneo Relámpago Fútbol 7', 'Campeonato Intercolegial Sub-18',
               'Copa Ciudad de la Costa', 'Liga de Verano Trinity', 'Copa Montevideo Norte',
               'Torneo Apertura Barrio Sur', 'Fútbol 5 Mixto Trinity Cup', 'Copa Amistad Canelones',
               'Desafío del Cerro', 'Copa Rambla Futsal'],
    'Brawl Stars': ['Brawl Stars Cup UTU', 'Showdown Trinity Open', 'Brawl Ball Championship',
                    'Copa Gemas Uruguay', 'Brawl Stars 3v3 Primavera', 'Liga Brawlers Montevideo',
                    'Torneo Heist Relámpago', 'Copa Estrellas del Sur', 'Brawl Stars Interliceos'],
    'Clash Royale': ['Clash Royale Open Montevideo', 'Copa Torre del Rey', 'Desafío Triple Elixir',
                     'Clash Royale Interliceos', 'Liga Arena Legendaria', 'Torneo Draft Trinity',
                     'Copa Mazo Libre', 'Clash Royale Cup Maldonado', 'Desafío Doble Elixir',
                     'Trinity Clash Masters'],
    'Fortnite': ['Fortnite Zero Build Showdown', 'Trinity Battle Royale Cup', 'Copa Isla Reload',
                 'Fortnite Duos Open', 'Torneo Creativo Box Fight', 'Liga Victory Royale',
                 'Fortnite Trios Primavera'],
    'Free Fire': ['Free Fire Squad Cup', 'Torneo Booyah Montevideo', 'Copa Bermuda Trinity',
                  'Free Fire Clash Squad Open', 'Liga Booyah UTU', 'Free Fire Relámpago',
                  'Copa Kalahari', 'Free Fire Interliceos'],
    'Minecraft': ['Minecraft Bedwars Championship', 'Copa Skyblock Trinity', 'Torneo Lucky Blocks Open',
                  'Minecraft Parkour Race', 'Liga SMP Trinity', 'Desafío Redstone',
                  'Copa Survival Games', 'Minecraft Construcción Express'],
}
DESCRIPCIONES = {
    'Fútbol': ['Partidos de 40 minutos, cancha de césped sintético. Traer canilleras y camiseta de color.',
               'Torneo abierto a toda la comunidad. Se juega los sábados por la tarde.',
               'Equipos de 5 jugadores. Árbitros designados por la organización.'],
    'default': ['Partidas al mejor de 3. Se juega en línea, con registro previo del tag de jugador.',
                'Torneo abierto a toda la comunidad. Las partidas se coordinan por la plataforma.',
                'Reglas estándar del juego, sin trucos ni cuentas compartidas. ¡A competir!'],
}

plan = (
    [('finalizado', 'eliminacion')] * 7 + [('finalizado', 'liga')] * 5 + [('finalizado', 'suizo')] * 6 +
    [('en_curso', 'eliminacion')] * 5 + [('en_curso', 'liga')] * 4 + [('en_curso', 'suizo')] * 3 +
    [('abierto', 'eliminacion')] * 5 + [('abierto', 'liga')] * 5 + [('abierto', 'suizo')] * 4 +
    [('en_creacion', 'eliminacion')] * 2 + [('en_creacion', 'liga')] * 2 + [('en_creacion', 'suizo')] * 2 +
    [('cancelado', 'eliminacion')] * 2 + [('cancelado', 'liga')] * 2 + [('cancelado', 'suizo')] * 2
)
assert len(plan) == 56
rng.shuffle(plan)

privados = set()
for estado, cantidad in [('finalizado', 3), ('en_curso', 3), ('abierto', 3), ('en_creacion', 2), ('cancelado', 1)]:
    idxs = [i for i, p in enumerate(plan) if p[0] == estado]
    privados |= set(rng.sample(idxs, cantidad))

titulos_planos = [(d, t) for d, ts_ in TITULOS.items() for t in ts_]
rng.shuffle(titulos_planos)
assert len(titulos_planos) == 56

TAM = {
    'eliminacion': [4, 5, 6, 7, 8, 8, 8, 10, 12, 6, 5, 8],
    'liga': [4, 4, 5, 6, 6, 6, 5, 4, 6],
    'suizo': [6, 7, 8, 8, 8, 6, 5, 7, 9],
}

T = []
for i, ((estado, formato), (deporte, titulo)) in enumerate(zip(plan, titulos_planos)):
    if estado == 'finalizado':
        inicio = -rng.randint(18, 150)
    elif estado == 'en_curso':
        inicio = -rng.randint(1, 14)
    elif estado == 'abierto':
        inicio = rng.randint(3, 50)
    elif estado == 'en_creacion':
        inicio = rng.randint(15, 90)
    else:
        inicio = rng.randint(-30, 20)
    creado = min(-1, inicio - rng.randint(6, 25)) if estado != 'abierto' else -rng.randint(1, 25)
    if estado == 'en_creacion':
        creado = -rng.randint(0, 8)
    T.append(dict(
        deporte=deporte, titulo=titulo, estado=estado, formato=formato,
        visibilidad='privado' if i in privados else 'publico',
        inicio=inicio, creado=creado, organizador=rng.choice(ORGANIZADORES),
        descripcion=rng.choice(DESCRIPCIONES['Fútbol'] if deporte == 'Fútbol' else DESCRIPCIONES['default']),
        participantes=[], partidos=[], campeon=None,
    ))
T.sort(key=lambda t: (t['creado'], t['titulo']))     # ids crecientes con el tiempo
for n, t in enumerate(T, 1):
    t['id'] = n


# ───────────── Simulación de partidos (réplica de MatchService) ─────────────
def etiqueta_ronda(r, total):
    return {0: 'Final', 1: 'Semifinal', 2: 'Cuartos de final', 3: 'Octavos de final',
            4: '16avos de final', 5: '32avos de final'}.get(total - r, f'Ronda {r}')


def bit_reverso(m):
    bits = 0
    while (1 << bits) < m:
        bits += 1
    out = []
    for i in range(m):
        r = 0
        for b in range(bits):
            if i & (1 << b):
                r |= 1 << (bits - 1 - b)
        out.append(r)
    return out


def partido(ronda, etq, orden, p1=None, p2=None, estado='pendiente', ganador=None, creado=0):
    return dict(ronda=ronda, etiqueta=etq, orden=orden, p1=p1, p2=p2, r1=None, r2=None,
                ganador=ganador, estado=estado, creado=creado)


def jugar(t, m, permitir_empate):
    """Decide el resultado de un partido con probabilidad según habilidad."""
    a, b = U[m['p1']]['skill'], U[m['p2']]['skill']
    p1_gana = rng.random() < 1 / (1 + math.exp(-(a - b)))
    if t['deporte'] == 'Fútbol':
        if permitir_empate and rng.random() < 0.2:
            g = rng.choice([0, 1, 1, 2, 2, 3])
            m['r1'] = m['r2'] = g
            m['ganador'] = None
            m['estado'] = 'jugado'
            return
        gan = rng.choices([1, 2, 3, 4, 5], [30, 35, 20, 10, 5])[0]
        per = rng.randint(0, gan - 1)
    else:                                  # mejor de 3 partidas
        gan, per = 2, rng.choice([0, 1])
    m['r1'], m['r2'] = (gan, per) if p1_gana else (per, gan)
    m['ganador'] = m['p1'] if p1_gana else m['p2']
    m['estado'] = 'jugado'


def avanzar(partidos, ronda, orden, ganador, total):
    if ronda >= total:
        return
    sig = next((x for x in partidos if x['ronda'] == ronda + 1 and x['orden'] == orden // 2), None)
    if sig:
        sig['p1' if orden % 2 == 0 else 'p2'] = ganador


def sim_eliminacion(t, ids, avance):
    n = len(ids)
    tam = 1
    while tam < n:
        tam *= 2
    total = int(math.log(tam) / math.log(2))
    r1 = tam // 2
    byes = tam - n
    con_bye = set(bit_reverso(r1)[:byes])
    slots, k = [], 0
    for i in range(r1):
        slots.append(ids[k]); k += 1
        if i in con_bye:
            slots.append(None)
        else:
            slots.append(ids[k]); k += 1
    P = t['partidos']
    bye_ganadores = {}
    for i in range(r1):
        p1, p2 = slots[2 * i], slots[2 * i + 1]
        if p1 is None and p2 is None:
            continue
        if p1 is None or p2 is None:
            g = p1 if p1 is not None else p2
            P.append(partido(1, etiqueta_ronda(1, total), i, p1, p2, 'wo', g, t['inicio']))
            bye_ganadores[i] = g
        else:
            P.append(partido(1, etiqueta_ronda(1, total), i, p1, p2, 'pendiente', None, t['inicio']))
    for r in range(2, total + 1):
        for i in range(tam // 2 ** r):
            P.append(partido(r, etiqueta_ronda(r, total), i, creado=t['inicio']))
    for orden, g in bye_ganadores.items():
        avanzar(P, 1, orden, g, total)

    # Juego: avance = (rondas completas, fracción de la siguiente) o 'todo'
    completas, fraccion = (total, 1) if avance == 'todo' else avance
    for r in range(1, total + 1):
        for m in sorted([x for x in P if x['ronda'] == r], key=lambda x: x['orden']):
            if m['estado'] != 'pendiente' or not m['p1'] or not m['p2']:
                continue
            if avance != 'todo':
                if r > completas + 1:
                    continue
                if r == completas + 1 and rng.random() > fraccion:
                    continue
            jugar(t, m, permitir_empate=False)
            avanzar(P, r, m['orden'], m['ganador'], total)
    final = next(x for x in P if x['ronda'] == total)
    return final['ganador'] if final['estado'] != 'pendiente' else None


def sim_liga(t, ids, avance):
    jug = ids[:]
    if len(jug) % 2:
        jug.append(None)
    n = len(jug)
    fechas = n - 1
    for f in range(1, fechas + 1):
        orden = 0
        for i in range(n // 2):
            a, b = jug[i], jug[n - 1 - i]
            if a is not None and b is not None:
                t['partidos'].append(partido(f, f'Fecha {f}', orden, a, b, creado=t['inicio']))
                orden += 1
        ultimo = jug.pop()
        jug.insert(1, ultimo)
    completas, fraccion = (fechas, 1) if avance == 'todo' else avance
    for m in t['partidos']:
        if m['ronda'] <= completas or (m['ronda'] == completas + 1 and rng.random() < fraccion):
            jugar(t, m, permitir_empate=True)


def tabla(t):
    est = {p: dict(id=p, pj=0, pg=0, pe=0, pp=0, pts=0) for p in t['participantes']}
    for m in t['partidos']:
        if m['estado'] == 'pendiente':
            continue
        for lado in (m['p1'], m['p2']):
            if lado is None or lado not in est:
                continue
            e = est[lado]
            e['pj'] += 1
            if m['ganador'] == lado:
                e['pg'] += 1; e['pts'] += 3
            elif m['ganador'] is None and m['p1'] is not None and m['p2'] is not None:
                e['pe'] += 1; e['pts'] += 1
            else:
                e['pp'] += 1
    return sorted(est.values(), key=lambda e: (-e['pts'], -e['pg'], e['id']))


def ronda_suiza(t, ids, ronda):
    P = t['partidos']
    puntos = {i: 0 for i in ids}
    ya = defaultdict(list)
    descansaron = []
    for m in P:
        if m['estado'] != 'pendiente':
            if m['ganador'] is not None:
                puntos[m['ganador']] = puntos.get(m['ganador'], 0) + 3
            elif m['p1'] and m['p2']:
                puntos[m['p1']] += 1; puntos[m['p2']] += 1
        if m['p1'] and m['p2']:
            ya[m['p1']].append(m['p2']); ya[m['p2']].append(m['p1'])
        if m['estado'] == 'wo' and m['p2'] is None and m['p1']:
            descansaron.append(m['p1'])
    orden_ = sorted(ids, key=lambda a: (-puntos.get(a, 0), a))
    creado = min(0, t['inicio'] + 2 * (ronda - 1))
    orden = 0
    if len(orden_) % 2:
        cand = None
        for a in reversed(orden_):
            if a not in descansaron:
                cand = a
                break
        if cand is None:
            cand = orden_[-1]
        orden_ = [x for x in orden_ if x != cand]
        P.append(partido(ronda, f'Ronda {ronda}', orden, cand, None, 'wo', cand, creado))
        orden += 1
    usados = set()
    for a in orden_:
        if a in usados:
            continue
        rival = next((b for b in orden_ if b != a and b not in usados and b not in ya[a]), None)
        if rival is None:
            rival = next((b for b in orden_ if b != a and b not in usados), None)
        if rival is None:
            continue
        usados.add(a); usados.add(rival)
        P.append(partido(ronda, f'Ronda {ronda}', orden, a, rival, creado=creado))
        orden += 1


def sim_suizo(t, ids, avance):
    total = max(1, math.ceil(math.log(max(len(ids), 2)) / math.log(2)))
    generadas, fraccion = (total, 1) if avance == 'todo' else avance
    for r in range(1, generadas + 1):
        ronda_suiza(t, ids, r)
        for m in [x for x in t['partidos'] if x['ronda'] == r]:
            if m['estado'] != 'pendiente' or not m['p1'] or not m['p2']:
                continue
            if r < generadas or avance == 'todo' or rng.random() < fraccion:
                jugar(t, m, permitir_empate=False)


def avance_aleatorio(t, n):
    """Cuánto del torneo en curso ya se jugó (siempre queda algo pendiente)."""
    if t['formato'] == 'eliminacion':
        tam = 1
        while tam < n:
            tam *= 2
        total = int(math.log(tam) / math.log(2))
        return (rng.randint(1, max(1, total - 1)), rng.choice([0, 0.4, 0.7]))
    if t['formato'] == 'liga':
        fechas = (n if n % 2 else n - 1)
        return (rng.randint(1, fechas - 1), rng.choice([0, 0.5]))
    total = max(1, math.ceil(math.log(max(n, 2)) / math.log(2)))
    return (rng.randint(1, total), rng.choice([0, 0.5]))


# Participantes y simulación, torneo por torneo
inscripciones = []          # (torneo_id, usuario_id, off_dias, minutos)
for t in T:
    est, fmt = t['estado'], t['formato']
    org = t['organizador']
    if est in ('en_curso', 'finalizado'):
        n = rng.choice(TAM[fmt])
        t['max'] = rng.choice([n, n, 8 if n <= 8 else 16, 16 if n <= 16 else 32])
    elif est == 'abierto':
        t['max'] = rng.choice([8, 12, 16, 32])
        n = rng.randint(0, t['max'] - 1)
    elif est == 'cancelado':
        t['max'] = rng.choice([8, 16])
        n = rng.randint(2, 5)
    else:
        t['max'] = rng.choice([8, 16, 32])
        n = 0
    t['max'] = max(t['max'], n)
    t['n'] = n

# Un torneo abierto lleno (cupo completo) para probar ese caso
primero_abierto = next(t for t in T if t['estado'] == 'abierto')
primero_abierto['max'] = 8
primero_abierto['n'] = 8

for t in T:
    cand = pool(t['deporte'], excluir=(t['organizador'],))
    rng.shuffle(cand)
    if len(cand) < t['n']:
        resto = [u for u in JUGADORES if u not in cand and u != t['organizador']]
        rng.shuffle(resto)
        cand += resto
    t['participantes'] = cand[:t['n']]
    for u in t['participantes']:
        lim = min(t['inicio'], -1) if t['estado'] != 'abierto' else -1
        lo = t['creado']
        off = rng.randint(min(lo, lim), lim) if lo < lim else lim
        inscripciones.append((t['id'], u, off, rng.randint(0, 600)))

    if t['estado'] in ('en_curso', 'finalizado'):
        ids = t['participantes'][:]
        rng.shuffle(ids)                       # igual que shuffle($ids) de MatchService::start
        avance = 'todo' if t['estado'] == 'finalizado' else avance_aleatorio(t, len(ids))
        if t['formato'] == 'eliminacion':
            campeon = sim_eliminacion(t, ids, avance)
        elif t['formato'] == 'liga':
            sim_liga(t, ids, avance)
        else:
            sim_suizo(t, ids, avance)
        if t['estado'] == 'finalizado':
            t['campeon'] = campeon if t['formato'] == 'eliminacion' else tabla(t)[0]['id']
            for u in t['participantes']:
                U[u]['jugados'] += 1
            U[t['campeon']]['ganados'] += 1
        pend = [m for m in t['partidos'] if m['estado'] == 'pendiente']
        if t['estado'] == 'en_curso':
            assert pend, f"{t['titulo']}: en_curso sin partidos pendientes"
        else:
            assert not pend and t['campeon'], f"{t['titulo']}: finalizado incompleto"

# ───────────────────────── Escritura: cabecera + limpieza ─────────────────────────
SQL.append("""-- ═══════════════════════════════════════════════════════════════════
--  TRINITY — Datos de prueba (seed)
--  ARCHIVO GENERADO por tools/generar-seed.py — no editar a mano.
--
--  · 50+ registros en cada tabla (ver database/extras/seed_verificar.sql).
--  · Corre con los triggers de auditoría ACTIVOS: cada fila sembrada
--    queda también en `auditoria` (usuario NULL, ip 'seed').
--  · Re-ejecutable: primero borra SOLO lo que sembró antes (emails
--    @trinity.test y teléfonos +598 90 ...) y lo vuelve a cargar.
--  · NO usar en producción. Los usuarios comparten contraseñas de
--    demostración (ver README) y los teléfonos son ficticios.
--  · notif_whatsapp = 0 en todos: aunque el bot esté conectado, el
--    seed nunca dispara mensajes a números reales.
--
--  Cuentas de prueba (login = email + contraseña):
--    admin@trinity.test         / Admin2026!         (rol admin)
--    organizador@trinity.test   / Organizador2026!   (rol organizador)
--    participante@trinity.test  / Participante2026!  (rol participante)
--    resto de los usuarios      / Trinity2026!
-- ═══════════════════════════════════════════════════════════════════
SET NAMES utf8mb4;
USE trinity;
SET @app_user_id = NULL, @app_ip = 'seed';

-- ── Limpieza de una siembra anterior (idempotencia) ──────────────────
-- Borrar los usuarios sembrados arrastra por CASCADE sus torneos,
-- inscripciones, partidos, equipos, noticias, solicitudes, etc.
DELETE FROM mensajes_contacto   WHERE contacto LIKE '%@trinity.test';
DELETE FROM codigos_verificacion WHERE destino LIKE '%@trinity.test' OR destino LIKE '+598 90 %';
DELETE FROM usuarios            WHERE email    LIKE '%@trinity.test' OR telefono LIKE '+598 90 %';
""")

# ───────────────────────── usuarios ─────────────────────────
DESC_FUT = ['Defensor de la vieja escuela, siempre al fondo.', 'Juego de 10 y la pelota es mía.',
            'Fútbol 5 todos los jueves, anotame.', 'Arquero por vocación (y por falta de voluntarios).',
            'Hincha de la celeste, jugador de los domingos.']
DESC_GAM = ['Main de Clash Royale desde la temporada 1.', 'Si hay torneo de Brawl, me anoto.',
            'Builder en Minecraft y speedrunner a ratos.', 'Booyah o nada.',
            'Me gusta competir y hacer equipo, sin tilt.']
filas = []
for uid in range(1, N_USUARIOS + 1):
    u = U[uid]
    incompleto = uid in INCOMPLETOS
    fut, juegos = u['fav_futbol'], u['fav_juegos']
    if incompleto:
        tipo, deportes, videojuegos = None, None, None
        fut = False
        juegos = []
    else:
        tipo = 'deportes' if fut and (not juegos or rng.random() < 0.6) else 'videojuegos'
        deportes = j(['Fútbol']) if fut else None
        videojuegos = j(juegos) if juegos else None
    nac = f'{rng.randint(1999, 2010)}-{rng.randint(1, 12):02d}-{rng.randint(1, 28):02d}'
    if uid in ORGANIZADORES + ADMINS:
        nac = f'{rng.randint(1984, 1999)}-{rng.randint(1, 12):02d}-{rng.randint(1, 28):02d}'
    mc = 'Minecraft' in juegos
    modos = rng.sample(MODOS_MC, rng.randint(1, 3)) if mc else None
    filas.append([
        uid, u['nombre'], Raw(f"'{nac}'"), u['usuario'], tipo, deportes, videojuegos,
        rng.choice(ROLES_FUTBOL) if fut else None,
        rng.randint(1, 30) if fut else None,
        rng.choice(CLUBES) if fut else None,
        rng.choice(ESTILOS_MC) if mc else None,
        rng.choice(['Construir primero, pelear después.', 'Recursos al inicio, ventaja al final.',
                    'Defender la base y salir en grupo.']) if mc else None,
        rng.choice(ESPECIALIDADES_MC) if mc else None,
        j(modos) if mc else None,
        u['password'], u['email'], u['telefono'],
        rng.choice(PRONOUNS) if rng.random() < 0.4 else None,
        None if incompleto else (rng.choice(DESC_FUT) if tipo == 'deportes' else rng.choice(DESC_GAM)),
        None, u['jugados'], u['ganados'], 0, u['rol'],
        ts(-(500 - uid * 6 + rng.randint(0, 5)) if uid <= 3 else -rng.randint(20, 400), rng.randint(0, 1200)),
    ])
insertar('usuarios',
         ['id', 'nombre', 'fecha_nacimiento', 'usuario', 'tipo', 'deportes_seleccionados',
          'videojuegos_seleccionados', 'futbol_rol', 'futbol_numero', 'futbol_equipo', 'mc_estilo',
          'mc_estrategia', 'mc_especialidad', 'mc_modos', 'password', 'email', 'telefono', 'pronouns',
          'descripcion', 'foto_url', 'torneos_jugados', 'torneos_ganados', 'notif_whatsapp', 'rol',
          'creado_en'], filas, lote=25)

# ───────────────────────── torneos / inscripciones / partidos ─────────────────────────
filas = []
for t in T:
    filas.append([t['id'], t['organizador'], t['titulo'], t['deporte'], t['descripcion'], t['formato'],
                  t['max'], fecha(t['inicio']), t['visibilidad'], None, t['estado'],
                  ts(t['creado'], rng.randint(0, 900))])
insertar('torneos', ['id', 'organizador_id', 'titulo', 'deporte', 'descripcion', 'formato',
                     'max_participantes', 'fecha_inicio', 'visibilidad', 'banner_url', 'estado', 'creado_en'],
         filas)

insertar('torneo_participantes', ['torneo_id', 'usuario_id', 'inscrito_en'],
         [[a, b, ts(c, d)] for a, b, c, d in inscripciones])

filas, pid = [], 0
for t in T:
    for m in t['partidos']:
        pid += 1
        m['id'] = pid
        filas.append([pid, t['id'], m['ronda'], m['etiqueta'], m['orden'], m['p1'], m['p2'],
                      m['r1'], m['r2'], m['ganador'], m['estado'], None, ts(min(0, m['creado']), 600)])
insertar('partidos', ['id', 'torneo_id', 'ronda', 'ronda_etiqueta', 'orden', 'participante1_id',
                      'participante2_id', 'resultado1', 'resultado2', 'ganador_id', 'estado',
                      'fecha_programada', 'creado_en'], filas)

# ───────────────────────── invitaciones a torneos ─────────────────────────
inv, ya_inv = [], set()


def invitar(t, u, estado, off):
    if (t['id'], u) in ya_inv or u == t['organizador']:
        return
    ya_inv.add((t['id'], u))
    inv.append([t['id'], t['organizador'], u, estado, ts(off, rng.randint(0, 700))])


for t in T:
    if t['visibilidad'] == 'privado':
        for u in t['participantes']:                       # entraron por invitación
            invitar(t, u, 'aceptada', min(t['creado'] + 1, -1))
    if t['estado'] in ('abierto', 'en_creacion'):
        libres = [u for u in JUGADORES if u not in t['participantes'] and u != t['organizador']]
        for u in rng.sample(libres, rng.randint(2, 5)):
            invitar(t, u, 'pendiente', -rng.randint(0, 6))
        for u in rng.sample([x for x in libres if (t['id'], x) not in ya_inv], 1):
            invitar(t, u, 'rechazada', -rng.randint(1, 10))
    if t['estado'] == 'cancelado':
        for u in rng.sample([x for x in JUGADORES if x not in t['participantes']], 2):
            invitar(t, u, 'rechazada', min(t['creado'] + 2, -1))
insertar('torneo_invitaciones', ['torneo_id', 'organizador_id', 'invitado_id', 'estado', 'creado_en'], inv)

# ───────────────────────── equipos y miembros ─────────────────────────
NOMBRES_EQ = ['Halcones del Cerro', 'Tiburones FC', 'Aurora Gaming', 'Pampa Esports', 'Charrúas Squad',
              'Barrio Sur FC', 'Rambla Squad', 'Pocitos United', 'Malvín Warriors', 'Carrasco Royals',
              'Unión Prado', 'Los Pumas UTU', 'Trueno Celeste', 'Garra Oriental', 'Cuervos del Norte',
              'Lobos de Maldonado', 'Sin Pausa', 'Tormenta Roja', 'Kraken Team', 'Fénix Esports',
              'Atlético Aguada', 'Nómades FC', 'Colibrí Gaming', 'Zorros del Este', 'Vikingos UY',
              'Titanes del Plata', 'Rayo Veloz', 'Pirata Squad', 'Dragones del Sur', 'Gatos Negros',
              'Cóndores FC', 'Furia Charrúa', 'La Banda del Puerto', 'Legión Tricolor', 'Albatros FC',
              'Panteras UTU', 'Sombras del Río', 'Centella Gaming', 'Búhos Nocturnos', 'Tropa del Barrio',
              'Los Mateadores', 'Quimera Esports', 'Cardenales FC', 'Orca Squad', 'Tucu Tucu Team',
              'Tigres de Salto', 'Guardianes del Norte', 'Los Pájaros', 'Corsarios FC', 'Madrugadores',
              'Última Ronda', 'Chivito Gaming']
assert len(NOMBRES_EQ) == 52
DESC_EQ = ['Equipo de amigos que compite por diversión y por la gloria.',
           'Entrenamos los fines de semana. Se aceptan ganas de aprender.',
           'Nacimos en el liceo y seguimos juntos.', 'Buscamos jugadores con compromiso y buena onda.']
cap_usados, eq, miembros = set(), [], []
aspirantes = JUGADORES + ORGANIZADORES
for i, nombre in enumerate(NOMBRES_EQ, 1):
    disc = DISCIPLINAS[i % 6] if i > 6 else DISCIPLINAS[i - 1]
    cands = [u for u in aspirantes if u not in cap_usados and (
        (disc == 'Fútbol' and U[u]['fav_futbol']) or disc in U[u]['fav_juegos'])]
    cap = rng.choice(cands or [u for u in aspirantes if u not in cap_usados])
    cap_usados.add(cap)
    off = -rng.randint(5, 200)
    eq.append([i, nombre, disc, rng.choice(DESC_EQ), None, cap, ts(off, rng.randint(0, 900))])
    miembros.append([i, cap, 'capitan', 'activo', ts(off, rng.randint(0, 900))])
    posibles = [u for u in pool(disc) + JUGADORES if u != cap]
    vistos_m = set()
    for u in rng.sample(list(dict.fromkeys(posibles)), rng.randint(3, 7)):
        if u in vistos_m:
            continue
        vistos_m.add(u)
        rol = 'suplente' if rng.random() < 0.2 else 'jugador'
        estado = 'invitado' if rng.random() < 0.2 else 'activo'
        miembros.append([i, u, rol, estado, ts(min(-1, off + rng.randint(1, 20)), rng.randint(0, 900))])
insertar('equipos', ['id', 'nombre', 'disciplina', 'descripcion', 'logo_url', 'capitan_id', 'creado_en'], eq)
insertar('equipo_miembros', ['equipo_id', 'usuario_id', 'rol', 'estado', 'creado_en'], miembros)

# ───────────────────────── noticias ─────────────────────────
nombre_de = lambda u: U[u]['nombre']
noticias = []   # (autor, titulo, resumen, contenido, categoria, off)
for t in T:
    if t['estado'] == 'finalizado':
        noticias.append((t['organizador'], f"{nombre_de(t['campeon'])} se consagró campeón de {t['titulo']}",
                         f"Cerró {t['titulo']} con {len(t['participantes'])} participantes.",
                         f"Terminó {t['titulo']} ({t['deporte']}). {nombre_de(t['campeon'])} se quedó con el título "
                         f"después de una competencia pareja. Gracias a todos los que participaron; ya se viene la próxima edición.",
                         'resultados', t['inicio'] + 10))
    elif t['estado'] == 'abierto':
        noticias.append((t['organizador'], f"Abren las inscripciones: {t['titulo']}",
                         f"Torneo de {t['deporte']}, cupo de {t['max']} participantes.",
                         f"Ya podés anotarte a {t['titulo']}. Formato: {t['formato']}. "
                         f"Cupo máximo: {t['max']} participantes. Inscribite desde la sección Torneos.",
                         'torneos', t['creado'] + 1))
PLATAFORMA = [
    ('Nueva sección de equipos', 'Creá tu equipo, invitá gente y competí en grupo.', 'actualizaciones'),
    ('Ranking por disciplina', 'Ahora podés filtrar el ranking por deporte o videojuego.', 'actualizaciones'),
    ('Mejoras en la versión móvil', 'Navegá Trinity cómodo desde el celular.', 'actualizaciones'),
    ('Historial de auditoría para administradores', 'Todos los cambios en la base de datos quedan registrados.', 'actualizaciones'),
    ('Tabla de posiciones en vivo', 'Liga y sistema suizo muestran las posiciones al instante.', 'actualizaciones'),
    ('Verificación por WhatsApp', 'Recibí tus códigos de verificación por WhatsApp.', 'actualizaciones'),
    ('Perfil con estadísticas de juego', 'Vinculá tu cuenta de Clash Royale, Brawl Stars o Fortnite.', 'actualizaciones'),
    ('Notificaciones dentro de la plataforma', 'Enterate de invitaciones e inicios de torneo.', 'actualizaciones'),
    ('Entrevista a un campeón de la temporada', 'Charlamos sobre estrategia, nervios y mate.', 'comunidad'),
    ('Guía: cómo organizar tu primer torneo', 'Paso a paso, de la idea a la final.', 'comunidad'),
    ('Los mejores momentos del trimestre', 'Remontadas, finales ajustadas y mucha pasión.', 'comunidad'),
    ('Conocé a los organizadores de Trinity', 'Quiénes están detrás de los torneos.', 'comunidad'),
    ('Fair play: nuestro código de conducta', 'Respeto, juego limpio y buena onda.', 'comunidad'),
    ('Torneos entre liceos: se viene la temporada', 'Sumá a tu centro educativo.', 'comunidad'),
    ('Equipos que dan que hablar', 'Repasamos los equipos más activos.', 'comunidad'),
    ('Cómo subir en el ranking', 'Ganar torneos es la clave; participar suma.', 'comunidad'),
    ('Mantenimiento programado', 'El sitio puede tener cortes breves el domingo de madrugada.', 'anuncios'),
    ('Reglamento general actualizado', 'Revisá los cambios en las reglas de convivencia.', 'anuncios'),
    ('Nuevos formatos disponibles', 'Liga, eliminación directa y sistema suizo.', 'anuncios'),
    ('Soporte por correo', 'Escribinos desde la sección Contacto.', 'anuncios'),
    ('Cambios en la política de privacidad', 'Actualizamos cómo cuidamos tus datos.', 'anuncios'),
    ('Calendario de torneos del mes', 'Mirá qué viene en las próximas semanas.', 'anuncios'),
]
for k, (tt, rs, cat) in enumerate(PLATAFORMA):
    noticias.append((rng.choice(ADMINS), tt, rs, rs + ' Más detalles en la plataforma.', cat, -rng.randint(3, 90)))
assert len(noticias) >= 50, len(noticias)

filas = []
for k, (autor, titulo, resumen, contenido, cat, off) in enumerate(noticias, 1):
    off = min(-1, off)
    if U[autor]['rol'] == 'admin':
        estado, res_por, res_en, pub = 'publicada', None, None, ts(off, 30)
    else:
        estado = rng.choices(['publicada', 'pendiente', 'rechazada'], [60, 25, 15])[0]
        if estado == 'pendiente':
            res_por, res_en, pub = None, None, None
        else:
            res_por = rng.choice(ADMINS)
            res_en = ts(min(-1, off + 1), 60)
            pub = ts(min(-1, off + 1), 60) if estado == 'publicada' else None
    filas.append([k, autor, titulo[:160], resumen[:280], contenido, cat, None, estado, res_por, res_en, pub, ts(off, 0)])
insertar('noticias', ['id', 'autor_id', 'titulo', 'resumen', 'contenido', 'categoria', 'imagen_url',
                      'estado', 'resuelto_por', 'resuelto_en', 'publicado_en', 'creado_en'], filas)

# ───────────────────────── solicitudes de organizador ─────────────────────────
sol = []
for u in ORGANIZADORES:
    off = -rng.randint(60, 300)
    sol.append([u, 'aprobado', ts(off), rng.choice(ADMINS), ts(off + 1, 120), ts(off)])
no_demo = [u for u in PARTICIPANTES if u != ID_PART_DEMO]
rechazados = rng.sample(no_demo, 22)
solo_pend = rng.sample([u for u in no_demo if u not in rechazados], 18)
for u in rechazados:
    off = -rng.randint(40, 200)
    sol.append([u, 'rechazado', ts(off), rng.choice(ADMINS), ts(off + 2, 300), ts(off)])
for u in rng.sample(rechazados, 6) + solo_pend:                  # 6 reintentos + 18 nuevos
    off = -rng.randint(0, 25)
    sol.append([u, 'pendiente', ts(off), None, None, ts(off)])
insertar('solicitudes_organizador', ['usuario_id', 'estado', 'terminos_aceptados_en', 'resuelto_por',
                                     'resuelto_en', 'creado_en'], sol)

# ───────────────────────── seguidores ─────────────────────────
campeones = [t['campeon'] for t in T if t['campeon']]
pares = set()
while len(pares) < 140:
    a = rng.randint(1, N_USUARIOS)
    b = rng.choice(campeones + list(range(1, N_USUARIOS + 1)))
    if a != b:
        pares.add((a, b))
seg = [[a, b, ts(-rng.randint(1, 180), rng.randint(0, 900))] for a, b in sorted(pares)]
insertar('seguidores', ['seguidor_id', 'seguido_id', 'creado_en'], seg)

# ───────────────────────── cuentas de videojuego ─────────────────────────
SLUG = {'Clash Royale': 'clashroyale', 'Brawl Stars': 'brawlstars', 'Fortnite': 'fortnite', 'Minecraft': 'minecraft'}
ALFA_TAG = '0289PYLQGRJCUV'
cuentas, ya_c = [], set()


def identificador(slug, u):
    base = sin_acentos(U[u]['usuario'])
    if slug == 'clashroyale':
        return ''.join(rng.choice(ALFA_TAG) for _ in range(rng.randint(7, 9)))
    if slug == 'brawlstars':
        return ''.join(rng.choice(ALFA_TAG) for _ in range(rng.randint(8, 9)))
    if slug == 'fortnite':
        return f'{base}_{rng.choice(["TTV", "YT", "UY", "FN"])}'[:40]
    return f'{base}{rng.choice(["MC", "_UY", "_Craft"])}'[:16]


for u in JUGADORES + ORGANIZADORES:
    for g in U[u]['fav_juegos']:
        if g in SLUG and rng.random() < 0.75:
            cuentas.append([u, SLUG[g], identificador(SLUG[g], u), ts(-rng.randint(2, 150)), ts(-rng.randint(150, 250))])
            ya_c.add((u, SLUG[g]))
while len(cuentas) < 60:                                         # asegura holgura sobre el mínimo
    u = rng.choice(JUGADORES)
    g = rng.choice(list(SLUG))
    if (u, SLUG[g]) in ya_c:
        continue
    ya_c.add((u, SLUG[g]))
    if g not in U[u]['fav_juegos']:
        U[u]['fav_juegos'].append(g)
    cuentas.append([u, SLUG[g], identificador(SLUG[g], u), ts(-rng.randint(2, 150)), ts(-rng.randint(150, 250))])
insertar('cuentas_videojuego', ['usuario_id', 'juego', 'identificador', 'actualizado_en', 'creado_en'], cuentas)

# ───────────────────────── notificaciones ─────────────────────────
notifs = []


def notif(u, tipo, titulo, msg, link, off):
    notifs.append([u, tipo, titulo, msg, link, 1 if off < -4 and rng.random() < 0.8 else 0, ts(off, rng.randint(0, 900))])


for t in T:
    link = f"/pages/nav/tournament/detalle.html?id={t['id']}"
    if t['estado'] in ('en_curso', 'finalizado'):
        for u in t['participantes']:
            desc = {'eliminacion': 'el cuadro de eliminación', 'liga': 'el calendario de la liga',
                    'suizo': 'la primera ronda'}[t['formato']]
            notif(u, 'torneo_inicio', 'El torneo arrancó', f"\"{t['titulo']}\" ya está en curso. Se generó {desc}.",
                  link, min(-1, t['inicio']))
    if t['estado'] == 'finalizado':
        notif(t['campeon'], 'torneo_campeon', '¡Ganaste el torneo! 🏆',
              f"Te consagraste campeón de \"{t['titulo']}\".", link, min(-1, t['inicio'] + 8))
    for u in t['participantes'][:3]:
        notif(t['organizador'], 'torneo_inscripcion', 'Nueva inscripción',
              f"Un nuevo participante se inscribió en \"{t['titulo']}\".", link, min(-1, t['creado'] + 2))
for i_, (tid, org, inv_id, est, _) in enumerate(inv):
    if est == 'pendiente':
        tt = next(x for x in T if x['id'] == tid)
        notif(inv_id, 'torneo_invitacion', f"Te invitaron al torneo \"{tt['titulo']}\"",
              f"{U[org]['nombre']} (@{U[org]['usuario']}) te envió una invitación. ¡Ingresá para aceptarla o rechazarla!",
              None, -rng.randint(0, 6))
for e in miembros:
    if e[3] == 'invitado':
        nom = NOMBRES_EQ[e[0] - 1]
        notif(e[1], 'equipo_invitacion', 'Invitación a equipo', f'Te invitaron a unirte a "{nom}".',
              f'/pages/teams/equipo.html?id={e[0]}', -rng.randint(0, 10))
for a, b, _ in seg[:30]:
    notif(b, 'seguidor', f"{U[a]['nombre']} empezó a seguirte",
          f"@{U[a]['usuario']} se sumó a tus seguidores en Trinity.", f'/pages/profile/acc/view.html?u={a}',
          -rng.randint(0, 40))
insertar('notificaciones', ['usuario_id', 'tipo', 'titulo', 'mensaje', 'link', 'leido', 'creado_en'], notifs)

# ───────────────────────── mensajes de contacto ─────────────────────────
ASUNTOS = [
    ('Consulta sobre inscripción', 'Hola, quería saber cómo me inscribo a un torneo y si tiene costo.'),
    ('No me llega el código de verificación', 'Intenté registrarme varias veces y no recibo el código.'),
    ('Quiero ser organizador', 'Me gustaría organizar torneos en mi liceo. ¿Qué tengo que hacer?'),
    ('Error al cargar un resultado', 'Cuando cargo un resultado en mi torneo me tira un error.'),
    ('Sugerencia: modo oscuro', 'Estaría buenísimo tener un modo claro y uno oscuro.'),
    ('Problema con mi perfil', 'No puedo cambiar mi foto de perfil desde el celular.'),
    ('Consulta por reglamento', '¿Dónde puedo ver el reglamento general de los torneos?'),
    ('Felicitaciones', 'Quería felicitarlos por la plataforma, muy buena para la comunidad gamer.'),
    ('Torneo para mi liceo', 'Somos un grupo de 3° año y queremos un torneo interno. ¿Nos ayudan?'),
    ('No puedo cambiar mi contraseña', 'El link de recuperación me dice que está vencido.'),
    ('Reporte de jugador', 'Quiero reportar a un jugador por mal comportamiento en un torneo.'),
    ('Duda sobre el sistema suizo', '¿Cuántas rondas se juegan en un torneo suizo?'),
]
msgs = []
for k in range(56):
    asunto, texto = ASUNTOS[k % len(ASUNTOS)]
    if rng.random() < 0.55:
        uid = rng.choice(JUGADORES)
        nombre, contacto = U[uid]['nombre'], f"{sin_acentos(U[uid]['usuario']).lower()}@trinity.test"
    else:
        uid = None
        a, b = rng.choice(M + F), rng.choice(S)
        nombre = f'{a} {b}'
        contacto = f"{sin_acentos(a).lower()}.{sin_acentos(b).lower()}{k}@trinity.test"
    off = -rng.randint(0, 120)
    msgs.append([uid, nombre, contacto, asunto, texto, 1 if off < -15 and rng.random() < 0.8 else 0, ts(off, rng.randint(0, 900))])
insertar('mensajes_contacto', ['usuario_id', 'nombre', 'contacto', 'asunto', 'mensaje', 'leido', 'creado_en'], msgs)

# ───────────────────────── tablas transitorias ─────────────────────────
# Todos los tokens/códigos ya vencieron o se usaron: no sirven para nada,
# y son deterministas (sha256), así que NO pueden ser válidos a propósito.
resets = []
for k in range(1, 53):
    u = rng.choice(JUGADORES)
    off = -rng.randint(3, 120)
    resets.append([u, hashlib.sha256(f'trinity-seed-reset-{k}'.encode()).hexdigest(),
                   Raw(f"(NOW() + INTERVAL {off} DAY + INTERVAL 15 MINUTE)"), rng.choice([0, 1, 1]), ts(off)])
insertar('password_resets', ['usuario_id', 'token', 'expira_en', 'usado', 'creado_en'], resets)

cods = []
for k in range(52):
    u = U[rng.choice(JUGADORES)]
    destino = u['email'] or u['telefono']
    off = -rng.randint(3, 120)
    cods.append([destino, f'{rng.randint(0, 999999):06d}', rng.choice(['registro', 'cambio_credencial', 'cambio_password']),
                 Raw(f"(NOW() + INTERVAL {off} DAY + INTERVAL 10 MINUTE)"), rng.choice([0, 1, 1]), ts(off)])
insertar('codigos_verificacion', ['destino', 'codigo', 'tipo', 'expira_en', 'usado', 'creado_en'], cods)

SQL.append("SET @app_user_id = NULL, @app_ip = NULL;\n")

with open(SALIDA, 'w', encoding='utf-8') as f:
    f.write('\n'.join(SQL))

# Resumen en consola
fin = [t for t in T if t['estado'] == 'finalizado']
print(f'Escribí {SALIDA}')
print(f'  usuarios={N_USUARIOS} torneos={len(T)} inscripciones={len(inscripciones)} partidos={pid} '
      f'invitaciones={len(inv)} equipos={len(eq)} miembros={len(miembros)} noticias={len(noticias)} '
      f'solicitudes={len(sol)} seguidores={len(seg)} cuentas={len(cuentas)} notifs={len(notifs)} '
      f'contacto={len(msgs)} resets={len(resets)} codigos={len(cods)}')
print('  finalizados:', len(fin), '| campeones distintos:', len({t["campeon"] for t in fin}))
