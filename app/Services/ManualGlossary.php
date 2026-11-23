<?php

namespace App\Services;

use App\Models\ManualTerm;

/**
 * Glosario inglés → español del gremio para leer los catálogos (desgaste, cazos, tornillería…).
 * Tipo: nm/nf = nombre masculino/femenino singular; nmp/nfp = plural; a = adjetivo que concuerda (se da en masculino singular);
 * p = expresión invariable que va detrás del nombre («de uso general»); x = palabra suelta que se sustituye tal cual.
 */
class ManualGlossary
{
    private const BASE = <<<'TXT'
bucket|cazo|nm
buckets|cazos|nmp
bucket assembly|conjunto de cazo|nm
bucket group|grupo de cazo|nm
bucket type|tipo de cazo|nm
bucket linkage|bieletas del cazo|nfp
bucket liner|forro del cazo|nm
tip|punta|nf
tips|puntas|nfp
tip size|medida de punta|nf
tip options|opciones de punta|nfp
tooth|diente|nm
teeth|dientes|nmp
number of teeth|n.º de dientes|nm
unitooth|diente enterizo|nm
adapter|portadientes|nm
adapters|portadientes|nmp
center adapter|portadientes central|nm
center adapters|portadientes centrales|nmp
corner adapter|portadientes de esquina|nm
corner adapters|portadientes de esquina|nmp
cutting edge|cuchilla|nf
cutting edges|cuchillas|nfp
edge|cuchilla|nf
edges|cuchillas|nfp
edge type|tipo de cuchilla|nm
base edge|cuchilla base|nf
base edges|cuchillas base|nfp
base edge assembly|conjunto de cuchilla base|nm
end bit|cantonera|nf
end bits|cantoneras|nfp
bit|pica|nf
bits|picas|nfp
corner|esquina|nf
corners|esquinas|nfp
segment|segmento|nm
segments|segmentos|nmp
center section|tramo central|nm
center sections|tramos centrales|nmp
section|tramo|nm
sections|tramos|nmp
sidecutter|cortador lateral|nm
sidecutters|cortadores laterales|nmp
sidebar|lateral del cazo|nm
sidebars|laterales del cazo|nmp
sidebar protector|protector de lateral|nm
sidebar protectors|protectores de lateral|nmp
corner protector|protector de esquina|nm
corner protectors|protectores de esquina|nmp
protector|protector|nm
protectors|protectores|nmp
guard|protector|nm
guards|protectores|nmp
shroud|protector|nm
shrouds|protectores|nmp
heel shroud|protector de talón|nm
heel shrouds|protectores de talón|nmp
heel|talón|nm
wear plate|placa de desgaste|nf
wear plates|placas de desgaste|nfp
plate wear|placa de desgaste|nf
wear strip|pletina de desgaste|nf
wear strips|pletinas de desgaste|nfp
wear bar|barra de desgaste|nf
wear bars|barras de desgaste|nfp
wear protection|protección contra el desgaste|nf
wear|desgaste|nm
plate|placa|nf
plates|placas|nfp
strip|pletina|nf
strips|pletinas|nfp
strap|pletina|nf
straps|pletinas|nfp
liner|forro|nm
liners|forros|nmp
bar|barra|nf
bars|barras|nfp
pin|pasador|nm
pins|pasadores|nmp
retainer|retenedor|nm
retainers|retenedores|nmp
retainer assembly|conjunto retenedor|nm
retainer assemblies|conjuntos retenedores|nmp
bolt|tornillo|nm
bolts|tornillos|nmp
plow bolt|tornillo de cabeza avellanada|nm
plow bolts|tornillos de cabeza avellanada|nmp
screw|tornillo|nm
screws|tornillos|nmp
nut|tuerca|nf
nuts|tuercas|nfp
washer|arandela|nf
washers|arandelas|nfp
hardware|tornillería|nf
flat head|de cabeza plana|p
hex|hexagonal|a
assembly|conjunto|nm
assemblies|conjuntos|nmp
group|grupo|nm
groups|grupos|nmp
arrangement|disposición|nf
component|componente|nm
components|componentes|nmp
related components|componentes relacionados|nmp
part|pieza|nf
parts|piezas|nfp
replacement parts|piezas de recambio|nfp
part number|referencia|nf
part numbers|referencias|nfp
part no.|referencia|nf
part #|referencia|nf
number|número|nm
numbers|números|nmp
qty.|cant.|nf
qty|cant.|nf
quantity|cantidad|nf
description|descripción|nf
model|modelo|nm
models|modelos|nmp
type|tipo|nm
size|medida|nf
width|ancho|nm
thickness|espesor|nm
length|longitud|nf
weight|peso|nm
capacity|capacidad|nf
dimension|medida|nf
dimensions|medidas|nfp
diameter|diámetro|nm
spacing|separación|nf
hole|agujero|nm
holes|agujeros|nmp
row|fila|nf
inch|pulgada|nf
inches|pulgadas|nfp
straight|recto|a
spade|en pico|p
curved|curvo|a
flat|plano|a
shaped|perfilado|a
serrated|dentado|a
sharp|afilado|a
angled|en ángulo|p
long|largo|a
short|corto|a
wide|ancho|a
thick|grueso|a
heavy duty|reforzado|a
twin|doble|p
carryover|de serie anterior|p
hd|reforzado|a
long life|de larga duración|p
slack adjuster|tensor|nm
slack adjuster group|grupo tensor|nm
more|más|x
other|otros|x
identification plate|placa de identificación|nf
n/s|sin despiece|x
non-serviced|sin despiece|p
extreme duty|para trabajo extremo|p
severe duty|para trabajo severo|p
heavy|pesado|a
light|ligero|a
standard|estándar|p
std|estándar|p
general purpose|de uso general|p
multi-purpose|multiuso|p
rock|roca|nf
penetration|penetración|nf
penetration plus|penetración plus|nf
abrasion|abrasión|nf
impact|impacto|nm
high|alto|a
low|bajo|a
large|grande|a
small|pequeño|a
new|nuevo|a
optional|opcional|a
additional|adicional|a
special|especial|a
available|disponible|a
recommended|recomendado|a
required|necesario|a
dedicated|específico|a
intermediate|intermedio|a
extended|alargado|a
front|delantero|a
rear|trasero|a
top|superior|a
upper|superior|a
bottom|inferior|a
lower|inferior|a
side|lateral|a
center|central|a
left|izquierdo|a
right|derecho|a
inner|interior|a
inside|interior|a
outer|exterior|a
outside|exterior|a
rh|dcha.|x
lh|izda.|x
r/l|dcha./izda.|x
l/r|izda./dcha.|x
bolt-on|atornillado|a
weld-on|soldado|a
pin-on|con pasador|p
two-bolt|de dos tornillos|p
one-bolt|de un tornillo|p
two-strap|de dos pletinas|p
flushmount|enrasado|a
mechanically attached|de fijación mecánica|p
half arrow|media flecha|nf
half-arrow|media flecha|nf
arrow|flecha|nf
excavator|excavadora|nf
excavators|excavadoras|nfp
loader|pala cargadora|nf
loaders|palas cargadoras|nfp
wheel loader|pala de ruedas|nf
wheel loaders|palas de ruedas|nfp
track loader|pala de cadenas|nf
track loaders|palas de cadenas|nfp
backhoe loader|retrocargadora|nf
backhoe loaders|retrocargadoras|nfp
backhoe|retro|nf
skid steer loader|minicargadora|nf
skid steer loaders|minicargadoras|nfp
bulldozer|bulldozer|nm
bulldozers|bulldozers|nmp
dozer|bulldozer|nm
compactor|compactador|nm
compactors|compactadores|nmp
motor grader|motoniveladora|nf
motor graders|motoniveladoras|nfp
scraper|traílla|nf
scrapers|traíllas|nfp
ripper|ripper|nm
rippers|rippers|nmp
scarifier|escarificador|nm
scarifiers|escarificadores|nmp
shank|vástago|nm
shanks|vástagos|nmp
hydraulic hammer|martillo hidráulico|nm
hydraulic hammers|martillos hidráulicos|nmp
hammer|martillo|nm
hammers|martillos|nmp
shovel|excavadora frontal|nf
shovels|excavadoras frontales|nfp
mining|minería|nf
underground mining|minería subterránea|nf
paving products|equipos de pavimentación|nmp
cold planer|fresadora|nf
cold planers|fresadoras|nfp
demolition tools|herramientas de demolición|nfp
rock drill tools|útiles de perforación|nmp
shear|cizalla|nf
shears|cizallas|nfp
shear block|taco de cizalla|nm
chisel|pica|nf
moil|puntero|nm
tool|herramienta|nf
tools|herramientas|nfp
service tools|herramientas de servicio|nfp
blade|hoja|nf
blades|hojas|nfp
moldboard|vertedera|nf
bowl|caja|nf
coupler|enganche rápido|nm
quick coupler|enganche rápido|nm
linkage|bieletas|nfp
arm|brazo|nm
stick|balancín|nm
boom|pluma|nf
machine|máquina|nf
machines|máquinas|nfp
digging|excavación|nf
excavating|excavación|nf
excavation|excavación|nf
trenching|zanjeo|nm
ditch cleaning|limpieza de cunetas|nf
handling|manipulación|nf
material handler|manipulación de materiales|nf
waste handler|manipulación de residuos|nf
material|material|nm
coal|carbón|nm
option|opción|nf
options|opciones|nfp
chart|tabla|nf
list|lista|nf
note|nota|nf
selection guide|guía de selección|nf
identification|identificación|nf
interchangeability|intercambiabilidad|nf
appendix|apéndice|nm
introduction|introducción|nf
system|sistema|nm
systems|sistemas|nmp
series|serie|nf
family|familia|nf
conversion|conversión|nf
installation|montaje|nm
removal|desmontaje|nm
weld|soldadura|nf
welding|soldadura|nf
torque|par de apriete|nm
block|taco|nm
shim|suplemento|nm
shims|suplementos|nmp
plug|tapón|nm
spacer|separador|nm
ring|anillo|nm
cover|tapa|nf
wedge|cuña|nf
nose|nariz|nf
life|duración|nf
steel|acero|nm
carbide|carburo|nm
application|aplicación|nf
applications|aplicaciones|nfp
piece|pieza|nf
set|juego|nm
kit|kit|nm
table of contents|índice|nm
and|y|x
or|o|x
with|con|x
without|sin|x
for|para|x
per|por|x
see|ver|x
page|pág.|x
yes|sí|x
none|ninguno|x
only|solo|x
includes|incluye|x
continued|continuación|x
cont'd.|cont.|x
cont'd|cont.|x
TXT;

    /** @return array<int,array{0:string,1:string,2:string,3:int}>  [inglés, español, tipo, id del término propio o 0] */
    public static function all(): array
    {
        $out = [];
        foreach (preg_split('/\R/', trim(self::BASE)) as $l) {
            [$en, $es, $t] = explode('|', $l);
            $out[$en] = [$en, $es, $t, 0];
        }
        try {
            foreach (ManualTerm::orderBy('id')->get() as $t) {
                $en = mb_strtolower(trim($t->en));
                $out[$en] = [$en, $t->es, $t->type, $t->id];
            }
        } catch (\Throwable $e) {
            // sin tabla todavía: solo el glosario base
        }

        return array_values($out);
    }

    /**
     * Para buscar en español: palabra del gremio → palabras inglesas del catálogo («cazo» → bucket, buckets).
     *
     * @return array<string,array<int,string>>
     */
    public static function reverse(): array
    {
        $fold = fn (string $s) => strtr(mb_strtolower($s), ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u']);
        $out = [];
        foreach (self::all() as [$en, $es, $t]) {
            if ($t === 'x' || str_contains($en, ' ') || preg_match('/[^a-z\-]/', $en)) {
                continue;
            }
            $w = $fold($es);
            if (! str_contains($w, ' ') && mb_strlen($w) >= 3) {
                $out[$w][] = $en;
            }
        }
        // Palabras de uso corriente cuyo equivalente del catálogo es una expresión
        foreach (['cantonera' => ['end bit'], 'cantoneras' => ['end bits'], 'cuchilla' => ['cutting edge', 'edge'], 'cuchillas' => ['cutting edges', 'edges'], 'pala' => ['loader'], 'palas' => ['loaders'],
            'minicargadora' => ['skid steer'], 'motoniveladora' => ['motor grader'], 'retro' => ['backhoe'], 'martillo' => ['hammer'], 'diente' => ['tooth', 'tip'], 'dientes' => ['teeth', 'tips'],
            'portapuntas' => ['adapter', 'adapters'], 'labio' => ['base edge'], 'bulon' => ['pin'], 'bulones' => ['pins']] as $w => $ens) {
            $out[$w] = array_values(array_unique(array_merge($out[$w] ?? [], $ens)));
        }

        return $out;
    }
}
