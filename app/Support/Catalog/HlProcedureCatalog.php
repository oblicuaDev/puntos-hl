<?php

namespace App\Support\Catalog;

/**
 * Catálogo real de procedimientos redimibles con puntos, tomado de la base
 * "BASE PROCEDIMIENTOS PUNTOS.xlsx — PROCEDIMIENTOS" que compartieron
 * Katherine y Daniela (H&L, 2026-11-25).
 *
 * - price_cop: columna VALOR (precio real del procedimiento). Los puntos NO
 *   se guardan: se derivan siempre de la tasa de redención vigente
 *   (settings.redemption_points_per_100_cop, 3 pts por cada $100).
 *   Nota: en la base, "NANOPORE CON MESOCIT" traía 6.000 pts para $660.000;
 *   H&L confirmó que el valor correcto es $660.000.
 * - Descripciones: adaptadas del contenido publicado en harkerlloreda.com
 *   (CPT hl_list_proced del WordPress). Los ítems marcados con
 *   'description_source' => 'redactada' no tienen página propia en el sitio
 *   y deben ser validados por la clínica.
 * - image: 'wp:<url>' = foto del sitio web de H&L; 'file:<nombre>' = foto
 *   adicional de la carpeta de Drive, versionada en resources/catalog/procedimientos.
 */
class HlProcedureCatalog
{
    public const CATEGORIES = [
        'procedimientos-corporales' => 'Procedimientos Corporales',
        'procedimientos-faciales' => 'Procedimientos Faciales',
        'cuidado-de-la-piel' => 'Cuidado de la Piel',
        'cuidado-capilar' => 'Cuidado Capilar',
        'bienestar-y-recuperacion' => 'Bienestar y Recuperación',
    ];

    /** Premios/procedimientos de demo que este catálogo reemplaza. */
    public const RETIRED_DEMO_REWARD_TITLES = [
        'Rejuvenecimiento facial con láser Spectra',
        'Restauración de volumen facial',
        'Fotorejuvenecimiento con luz pulsada',
        'Lifting facial no quirúrgico',
        'Drenaje linfático',
        'Masaje post operatorio',
        'Remodelación corporal con radiofrecuencia',
        'Musculptech',
        'Peeling facial',
        'Hollywood Peel',
        'Microdermoabrasión',
        'Mesoterapia facial polirevitalizante',
        'Regeneración capilar con PRP',
    ];

    public const RETIRED_DEMO_PROCEDURE_NAMES = [
        'Rinoplastia', 'Liposucción', 'Aumento de busto', 'Lifting facial', 'Blefaroplastia',
        'Abdominoplastia', 'Toxina botulínica', 'Rellenos dérmicos', 'Mesoterapia', 'Carboxiterapia',
    ];

    private const WP = 'https://webadmin.harkerlloreda.com/wp-content/uploads/2024/04/';

    public static function items(): array
    {
        $venus = [
            'El Venus Legacy es una solución no invasiva para mejorar la apariencia del cuerpo sin cirugía. Combina radiofrecuencia multipolar y campos electromagnéticos pulsados para estimular la producción de colágeno y elastina.',
            'Ayuda a reducir la apariencia de la celulitis, mejora la firmeza y elasticidad de la piel y contribuye a una silueta más definida en zonas como abdomen, muslos y glúteos, sin tiempo de inactividad.',
        ];
        $venusBenefits = [
            'Reducción de la apariencia de la celulitis.',
            'Piel más firme y con menos flacidez.',
            'Contorno corporal más definido.',
            'Sin tiempo de recuperación.',
        ];

        $musculptech = [
            'MUSCULPTECH es un tratamiento corporal no invasivo que, con tecnología electromagnética de 7 teslas, genera miles de contracciones musculares intensas en cada sesión.',
            'Ayuda a tonificar, aumentar masa muscular y reducir grasa localizada en abdomen, glúteos, brazos y muslos, con resultados progresivos y sin recuperación.',
        ];
        $musculptechBenefits = [
            'Tonificación y aumento de masa muscular.',
            'Ayuda a reducir la grasa localizada.',
            'Equivale a miles de abdominales o sentadillas sin esfuerzo físico.',
            'Sin tiempo de recuperación.',
        ];

        $hydrafacialBenefits = [
            'Limpieza profunda que elimina impurezas y células muertas.',
            'Hidratación intensa con antioxidantes y ácido hialurónico.',
            'Ayuda a minimizar la apariencia de los poros.',
            'Resultados visibles desde la primera sesión.',
        ];

        return [
            // ───────────── Procedimientos corporales ─────────────
            [
                'title' => 'Paquete 10 sesiones Venus Legacy (60 min)',
                'source_name' => 'PAQUETE 10 SESIONES DE VENUS DE 60 MIN',
                'category' => 'procedimientos-corporales',
                'price_cop' => 1270000,
                'short_description' => 'Diez sesiones de 60 minutos de Venus Legacy para reafirmar la piel, reducir la celulitis y definir el contorno corporal.',
                'paragraphs' => [...$venus, 'Este paquete incluye 10 sesiones de 60 minutos, el plan recomendado para ver resultados progresivos y sostenidos.'],
                'benefits' => $venusBenefits,
                'image' => 'file:paquete-10-sesiones-venus-60-min.png',
                'featured' => true,
            ],
            [
                'title' => 'Paquete 10 sesiones Musculptech (60 min)',
                'source_name' => 'PAQUETE 10 SESIONES DE MUSCULPTECH 60 MINUTOS',
                'category' => 'procedimientos-corporales',
                'price_cop' => 1100000,
                'short_description' => 'Diez sesiones de 60 minutos de Musculptech para tonificar, ganar masa muscular y reducir grasa localizada.',
                'paragraphs' => [...$musculptech, 'Este paquete incluye 10 sesiones de 60 minutos.'],
                'benefits' => $musculptechBenefits,
                'image' => 'wp:'.self::WP.'REMODELACION-CORPORAL-destacada-prevencion-e1744303667953.webp',
            ],
            [
                'title' => 'Venus Legacy — sesión de 90 min',
                'source_name' => 'VENUS LEGACY SESION 90 MIN',
                'category' => 'procedimientos-corporales',
                'price_cop' => 280000,
                'short_description' => 'Sesión de 90 minutos de Venus Legacy: radiofrecuencia para reafirmar la piel y remodelar el contorno corporal.',
                'paragraphs' => [...$venus, 'Sesión individual de 90 minutos.'],
                'benefits' => $venusBenefits,
                'image' => 'file:venus-legacy-90-min.png',
            ],
            [
                'title' => 'Venus Legacy — sesión de 60 min',
                'source_name' => 'VENUS LEGACY SESION 60 MIN',
                'category' => 'procedimientos-corporales',
                'price_cop' => 220000,
                'short_description' => 'Sesión de 60 minutos de Venus Legacy: radiofrecuencia para reafirmar la piel y reducir la celulitis.',
                'paragraphs' => [...$venus, 'Sesión individual de 60 minutos.'],
                'benefits' => $venusBenefits,
                'image' => 'wp:'.self::WP.'REMODELACION-CORPORAL-destacada.webp',
            ],
            [
                'title' => 'Musculptech — sesión de 90 min',
                'source_name' => 'MUSCULPTECH SESION DE 90 MIN',
                'category' => 'procedimientos-corporales',
                'price_cop' => 250000,
                'short_description' => 'Sesión de 90 minutos de Musculptech: contracciones musculares de 7 teslas para tonificar y definir.',
                'paragraphs' => [...$musculptech, 'Sesión individual de 90 minutos.'],
                'benefits' => $musculptechBenefits,
                'image' => 'wp:'.self::WP.'REMODELACION-CORPORAL-destacada-prevencion-e1744303667953.webp',
            ],
            [
                'title' => 'Musculptech — sesión de 60 min',
                'source_name' => 'MUSCULPTECH SESION DE 60 MIN',
                'category' => 'procedimientos-corporales',
                'price_cop' => 200000,
                'short_description' => 'Sesión de 60 minutos de Musculptech para tonificar músculos y reducir grasa localizada.',
                'paragraphs' => [...$musculptech, 'Sesión individual de 60 minutos.'],
                'benefits' => $musculptechBenefits,
                'image' => 'wp:'.self::WP.'REMODELACION-CORPORAL-destacada-prevencion-e1744303667953.webp',
            ],
            [
                'title' => 'Mesoterapia corporal',
                'source_name' => 'MESOTERAPIA CORPORAL',
                'category' => 'procedimientos-corporales',
                'price_cop' => 280000,
                'short_description' => 'Microinyecciones para reducir medidas, manejar la fibrosis y mejorar la apariencia de la celulitis.',
                'paragraphs' => [
                    'La mesoterapia corporal es un tratamiento no invasivo que se adapta a las necesidades de cada paciente para reducir medidas, manejar la fibrosis y mejorar la celulitis.',
                    'Mediante microinyecciones de activos en las zonas a tratar, ayuda a disminuir la acumulación de grasa localizada y a mejorar la textura y la elasticidad de la piel.',
                ],
                'benefits' => [
                    'Ayuda a reducir medidas en zonas localizadas.',
                    'Contribuye a disolver la fibrosis.',
                    'Mejora la apariencia de la celulitis.',
                ],
                'image' => 'wp:'.self::WP.'REDUCCION-DE-MEDIDAS-MANEJO-DE-FIBROSIS-Y-CELULITIS-destacada.webp',
            ],
            [
                'title' => 'Indiba / sesión posoperatoria',
                'source_name' => 'INDIBA/ POSOPERATORIOS',
                'category' => 'procedimientos-corporales',
                'price_cop' => 180000,
                'short_description' => 'Sesión de recuperación posoperatoria con radiofrecuencia Indiba para desinflamar y acelerar la recuperación.',
                'paragraphs' => [
                    'El cuidado posoperatorio es una parte esencial de la recuperación después de una cirugía. Nuestro equipo terapéutico adapta cada sesión a las necesidades específicas de tu procedimiento.',
                    'La radiofrecuencia Indiba ayuda a mejorar la circulación, estimular la regeneración de los tejidos y reducir la inflamación, favoreciendo una recuperación más rápida y cómoda.',
                ],
                'benefits' => [
                    'Mejora la circulación linfática y sanguínea.',
                    'Estimula la regeneración de los tejidos.',
                    'Ayuda a reducir la inflamación.',
                    'Contribuye a prevenir complicaciones posoperatorias.',
                ],
                'image' => 'wp:'.self::WP.'MASAJES-POST-OPERATORIOS-destacada.webp',
                'description_source' => 'redactada',
            ],

            // ───────────── Procedimientos faciales ─────────────
            [
                'title' => 'Exosomas faciales o capilares con Nanopore',
                'source_name' => 'EXOSOMAS FACIALES O CAPILARES CON NANOPORE',
                'category' => 'procedimientos-faciales',
                'price_cop' => 950000,
                'short_description' => 'Medicina regenerativa aplicada con Nanopore: estimula la reparación celular y el colágeno en rostro o cuero cabelludo.',
                'paragraphs' => [
                    'Los exosomas son nanopartículas biológicas cargadas de factores de crecimiento y proteínas que activan procesos de reparación profunda en la piel.',
                    'Aplicados con Nanopore, estimulan la producción de colágeno y elastina, mejoran la calidad de la piel y ayudan a reducir arrugas, flacidez y manchas. También pueden aplicarse en el cuero cabelludo. Es un procedimiento mínimamente invasivo, con resultados naturales y progresivos.',
                ],
                'benefits' => [
                    'Regeneración celular acelerada.',
                    'Más colágeno y elastina: piel más firme y elástica.',
                    'Mejor hidratación y luminosidad.',
                    'Mínimamente invasivo y apto para todo tipo de piel.',
                ],
                'image' => 'wp:'.self::WP.'MESOTERAPIA-FACIAL-POLIREVITTALIZANTE-destacada-e1744302380604.webp',
                'featured' => true,
            ],
            [
                'title' => 'Nanopore con Mesocit',
                'source_name' => 'NANOPORE CON MESOCIT',
                'category' => 'procedimientos-faciales',
                'price_cop' => 660000,
                'short_description' => 'Revitalización facial con Nanopore y el cóctel Mesocit para una piel más hidratada, luminosa y uniforme.',
                'paragraphs' => [
                    'Tratamiento facial que combina la tecnología Nanopore con Mesocit, un cóctel de activos revitalizantes, para potenciar su penetración en la piel.',
                    'Ayuda a mejorar la hidratación, la luminosidad y la textura del rostro, con un procedimiento mínimamente invasivo y sin tiempo de inactividad prolongado.',
                ],
                'benefits' => [
                    'Piel más hidratada y luminosa.',
                    'Mejora la textura y el tono.',
                    'Mínimamente invasivo.',
                ],
                'image' => 'file:nanopore-con-mesocit.png',
                'description_source' => 'redactada',
            ],
            [
                'title' => 'NCTF — mesoterapia facial polirevitalizante',
                'source_name' => 'NCTF',
                'category' => 'procedimientos-faciales',
                'price_cop' => 300000,
                'short_description' => 'Fórmula de más de 50 activos (vitaminas, aminoácidos y ácido hialurónico) para revitalizar e hidratar la piel.',
                'paragraphs' => [
                    'El NCTF (Nuevo Tratamiento Celular de la Farmacopea Francesa) es una fórmula desarrollada por los laboratorios Filorga con más de 50 ingredientes activos, entre ellos vitaminas, minerales, aminoácidos y ácido hialurónico.',
                    'Estimula la regeneración celular y la producción de colágeno y elastina, para una piel más firme, hidratada y de aspecto más joven.',
                ],
                'benefits' => [
                    'Rejuvenecimiento cutáneo y reducción de líneas finas.',
                    'Hidratación profunda con ácido hialurónico.',
                    'Piel más radiante y elástica.',
                ],
                'image' => 'wp:'.self::WP.'MESOTERAPIA-FACIAL-POLIREVITTALIZANTE-02.webp',
            ],
            [
                'title' => 'Mesoterapia — sesión',
                'source_name' => 'MESOTERAPIA SESIÓN',
                'category' => 'procedimientos-faciales',
                'price_cop' => 190000,
                'short_description' => 'Sesión de mesoterapia: microinyecciones de vitaminas y activos para revitalizar la piel.',
                'paragraphs' => [
                    'La mesoterapia consiste en microinyecciones de un cóctel de vitaminas, aminoácidos, antioxidantes y otros activos aplicados directamente en la zona a tratar.',
                    'Estimula la regeneración celular y mejora la hidratación, la firmeza y la luminosidad de la piel.',
                ],
                'benefits' => [
                    'Revitaliza e hidrata la piel.',
                    'Estimula la regeneración celular.',
                    'Procedimiento rápido y mínimamente invasivo.',
                ],
                'image' => 'wp:'.self::WP.'MESOTERAPIA-FACIAL-POLIREVITTALIZANTE-ellos.webp',
            ],
            [
                'title' => 'Plasma rico en plaquetas — sesión',
                'source_name' => 'PLASMA SESION',
                'category' => 'procedimientos-faciales',
                'price_cop' => 250000,
                'short_description' => 'Regeneración celular con tu propio plasma rico en plaquetas para estimular el colágeno y la luminosidad.',
                'paragraphs' => [
                    'El plasma rico en plaquetas revitaliza la piel desde adentro: se extrae una muestra de sangre, se centrifuga para obtener el plasma y se aplica en las zonas a tratar.',
                    'Estimula la producción natural de colágeno y elastina para una piel más tersa, luminosa y de mejor calidad. Se recomiendan mínimo 3 sesiones para un cambio notable.',
                ],
                'benefits' => [
                    'Estimulación del colágeno.',
                    'Mayor luminosidad.',
                    'Suaviza arrugas y líneas de expresión.',
                ],
                'image' => 'wp:'.self::WP.'REGENERACION-CELULAR-FACIAL-destacada.webp',
            ],
            [
                'title' => 'Luz pulsada (fotorejuvenecimiento)',
                'source_name' => 'LUZ PULSADA',
                'category' => 'procedimientos-faciales',
                'price_cop' => 267000,
                'short_description' => 'Pulsos de luz intensa (IPL) para tratar manchas, unificar el tono y estimular el colágeno.',
                'paragraphs' => [
                    'La luz pulsada es una técnica versátil que emite pulsos de luz intensa que penetran en las capas profundas de la piel y se absorben selectivamente por los pigmentos.',
                    'Permite tratar manchas solares, pecas e hiperpigmentación, y estimula el colágeno para mejorar la firmeza, sin dañar el tejido circundante.',
                ],
                'benefits' => [
                    'Ayuda a eliminar manchas pigmentarias.',
                    'Tono más uniforme.',
                    'Estimula la producción de colágeno.',
                ],
                'image' => 'wp:'.self::WP.'FOTOREJUVENECIMIENTO-02.webp',
            ],
            [
                'title' => 'Nd:YAG para manchas puntuales',
                'source_name' => 'ND YAG MANCHAS PUNTUALES',
                'category' => 'procedimientos-faciales',
                'price_cop' => 400000,
                'short_description' => 'Láser Nd:YAG dirigido a manchas puntuales para despigmentar y mejorar la calidad de la piel.',
                'paragraphs' => [
                    'Tratamiento de despigmentación con láser Nd:YAG enfocado en manchas puntuales, con un enfoque personalizado según el tipo de mancha de cada paciente.',
                    'Actúa sobre el pigmento de forma precisa, con resultados que comienzan a verse en pocas semanas y una mejora notable en la uniformidad de la piel.',
                ],
                'benefits' => [
                    'Tratamiento personalizado por tipo de mancha.',
                    'Despigmentación precisa.',
                    'Mejora la uniformidad y la calidad de la piel.',
                ],
                'image' => 'wp:'.self::WP.'TRATAMIENTO-EXCLUSIVO-PARA-MANCHAS-destacada.webp',
            ],

            // ───────────── Cuidado de la piel ─────────────
            [
                'title' => 'Combo Piel de Porcelana (Hydrafacial + Hollywood Peel)',
                'source_name' => 'COMBO PIEL PORCELANA (HYDRAFACIAL + HOLLYWOOD)',
                'category' => 'cuidado-de-la-piel',
                'price_cop' => 820000,
                'short_description' => 'Hydrafacial y Hollywood Peel en una sola experiencia para una piel luminosa, limpia y rejuvenecida.',
                'paragraphs' => [
                    'El Combo Piel de Porcelana fusiona dos tratamientos: Hydrafacial, que limpia, exfolia, extrae impurezas y nutre la piel con antioxidantes y ácido hialurónico, y Hollywood Peel, que revitaliza la piel con una mascarilla de carbón y láser Q-switch Nd:YAG.',
                    'El resultado es una piel radiante, rejuvenecida y con menos imperfecciones, sin tiempo de recuperación.',
                ],
                'benefits' => [
                    'Rejuvenecimiento integral de la piel.',
                    'Luminosidad instantánea.',
                    'Hidratación profunda.',
                    'Sin tiempo de recuperación.',
                ],
                'image' => 'wp:'.self::WP.'COMBO-PIEL-DE-PORCELANA-destacada.webp',
                'featured' => true,
            ],
            [
                'title' => 'Hollywood Peel (Nd:YAG Spectra Peel / Carbon Peel)',
                'source_name' => 'NDYAG SPECTRAPEEL /CARBON (HOLLYWOOD PEEL) NDYAG',
                'category' => 'cuidado-de-la-piel',
                'price_cop' => 660000,
                'short_description' => 'Mascarilla de carbón activado con láser Q-switch Nd:YAG para reducir poros, pigmentación y grasa.',
                'paragraphs' => [
                    'El Hollywood Peel combina una mascarilla de carbón con láser Q-switch Nd:YAG para revitalizar la piel sin procedimientos invasivos ni largos periodos de recuperación.',
                    'Reduce la apariencia de los poros abiertos, las pigmentaciones y otros signos de envejecimiento, para una piel más fresca y radiante.',
                ],
                'benefits' => [
                    'Reduce poros abiertos y pigmentaciones.',
                    'Procedimiento no invasivo y seguro.',
                    'Mejoría rápida en la apariencia de la piel.',
                ],
                'image' => 'wp:'.self::WP.'HOLLYWOOD-PEEL-destacada.webp',
            ],
            [
                'title' => 'Hydrafacial JLO facial',
                'source_name' => 'HYDRAFACIAL JLO FACIAL',
                'category' => 'cuidado-de-la-piel',
                'price_cop' => 450000,
                'short_description' => 'Hydrafacial edición JLO: limpia, extrae e hidrata la piel en una sola sesión para un glow inmediato.',
                'paragraphs' => [
                    'Hydrafacial limpia, extrae e hidrata la piel en una sola sesión con un proceso 100% automatizado, infundiendo antioxidantes y ácido hialurónico.',
                    'La versión JLO potencia el acabado luminoso de la piel desde la primera sesión.',
                ],
                'benefits' => $hydrafacialBenefits,
                'image' => 'wp:'.self::WP.'Hydrafacial-destacada.webp',
            ],
            [
                'title' => 'Hydrafacial de espalda',
                'source_name' => 'HYDRAFACIAL DE ESPALDA',
                'category' => 'cuidado-de-la-piel',
                'price_cop' => 550000,
                'short_description' => 'La limpieza profunda e hidratación de Hydrafacial aplicada a la espalda.',
                'paragraphs' => [
                    'La tecnología Hydrafacial aplicada a la piel de la espalda: limpieza profunda, exfoliación, extracción de impurezas e hidratación con antioxidantes y ácido hialurónico.',
                    'Ideal para mejorar la textura y el aspecto de una zona propensa a obstrucción de poros e imperfecciones.',
                ],
                'benefits' => $hydrafacialBenefits,
                'image' => 'wp:'.self::WP.'Hydrafacial-02.webp',
            ],
            [
                'title' => 'Peeling PRX',
                'source_name' => 'PEELING PRX',
                'category' => 'cuidado-de-la-piel',
                'price_cop' => 500000,
                'short_description' => 'Peeling médico PRX para renovar la piel, mejorar textura y luminosidad sin descamación visible.',
                'paragraphs' => [
                    'El peeling facial es un procedimiento no invasivo que renueva la piel, mejorando su textura, tono y apariencia general.',
                    'El peeling PRX estimula la renovación de la piel y ayuda a tratar arrugas, manchas, acné y cicatrices, con una recuperación mínima.',
                ],
                'benefits' => [
                    'Renovación celular.',
                    'Mejora textura, tono y luminosidad.',
                    'Recuperación mínima.',
                ],
                'image' => 'wp:'.self::WP.'PEELING-FACIAL-destacada.webp',
            ],
            [
                'title' => 'Peeling Sesderma',
                'source_name' => 'PEELING SESDERMA',
                'category' => 'cuidado-de-la-piel',
                'price_cop' => 380000,
                'short_description' => 'Peeling químico Sesderma para exfoliar, aclarar manchas y devolver la luminosidad a la piel.',
                'paragraphs' => [
                    'El peeling facial utiliza una solución química para exfoliar y renovar la piel, mejorando su textura, tono y apariencia general.',
                    'Con la línea de peelings Sesderma, el tratamiento se personaliza según las necesidades de cada piel: luminosidad, despigmentación e hidratación.',
                ],
                'benefits' => [
                    'Renovación celular.',
                    'Ayuda a reducir manchas y marcas.',
                    'Tratamiento personalizable.',
                ],
                'image' => 'wp:'.self::WP.'PEELING-FACIAL-02.webp',
            ],
            [
                'title' => 'Limpieza facial avanzada con microdermoabrasión',
                'source_name' => 'SESIÓN LIMPIEZA FACIAL AVANZADA MICRODERMOABRASIÓN',
                'category' => 'cuidado-de-la-piel',
                'price_cop' => 300000,
                'short_description' => 'Limpieza facial en 3 pasos con puntas de diamante y mascarilla de hidratación profunda.',
                'paragraphs' => [
                    'Una limpieza facial revitalizante en tres pasos: masaje linfático activador, abrasión con puntas de diamante para una exfoliación precisa y mascarilla de hidratación profunda.',
                    'Elimina suavemente la piel muerta, las imperfecciones y el exceso de grasa, dejando la piel fresca, suave y renovada.',
                ],
                'benefits' => [
                    'Renueva la piel.',
                    'Mejora la textura y reduce la apariencia de poros.',
                    'Estimula la regeneración celular.',
                ],
                'image' => 'wp:'.self::WP.'MICRODERMOABRASION-destacada-e1744301119605.webp',
            ],

            // ───────────── Cuidado capilar ─────────────
            [
                'title' => 'Mesoterapia capilar',
                'source_name' => 'MESOTERAPIA CAPILAR',
                'category' => 'cuidado-capilar',
                'price_cop' => 242000,
                'short_description' => 'Microinyecciones de vitaminas, aminoácidos y factores de crecimiento para fortalecer el cabello.',
                'paragraphs' => [
                    'La mesoterapia capilar es un tratamiento de bioestimulación que favorece la reactivación de las células capilares, con un cóctel de vitaminas, aminoácidos, antioxidantes y factores de crecimiento aplicado mediante microinyecciones en el cuero cabelludo.',
                    'Revitaliza la raíz de los folículos para que produzcan un cabello más fuerte y sano, y rejuvenece el cuero cabelludo favoreciendo el rebrote.',
                ],
                'benefits' => [
                    'Estimula el crecimiento capilar.',
                    'Rejuvenece el cuero cabelludo.',
                    'Nutre y fortalece los folículos pilosos.',
                ],
                'image' => 'wp:'.self::WP.'RECUPERACION-CAPILAR-DESTACADA-1.webp',
                'featured' => true,
            ],

            // ───────────── Bienestar y recuperación ─────────────
            [
                'title' => 'Sueroterapia con vitamina C',
                'source_name' => 'SUEROTERAPIA VITAMINA C',
                'category' => 'bienestar-y-recuperacion',
                'price_cop' => 320000,
                'short_description' => 'Terapia intravenosa con altas dosis de vitamina C, antioxidante que apoya el sistema inmune y la luminosidad de la piel.',
                'paragraphs' => [
                    'La sueroterapia es una terapia intravenosa regenerativa que aporta nutrientes directamente al torrente sanguíneo para una absorción completa.',
                    'La fórmula con vitamina C aporta un potente antioxidante que apoya el sistema inmune, la producción de colágeno y la vitalidad general.',
                ],
                'benefits' => [
                    'Acción antioxidante.',
                    'Apoya el sistema inmune.',
                    'Contribuye a la producción de colágeno.',
                ],
                'image' => 'file:sueroterapia-vitamina-c.png',
                'featured' => true,
                'description_source' => 'redactada',
            ],
            [
                'title' => 'Sueroterapia',
                'source_name' => 'SUEROTERAPIA',
                'category' => 'bienestar-y-recuperacion',
                'price_cop' => 280000,
                'short_description' => 'Terapia intravenosa regenerativa con vitaminas y minerales para recargar energía y bienestar.',
                'paragraphs' => [
                    'La sueroterapia es una terapia intravenosa regenerativa que aporta vitaminas, minerales y antioxidantes directamente al torrente sanguíneo, para una absorción completa.',
                    'Ayuda a recuperar la energía, apoya la hidratación del organismo y complementa los tratamientos estéticos y de recuperación.',
                ],
                'benefits' => [
                    'Absorción completa de nutrientes.',
                    'Más energía y bienestar.',
                    'Complementa tratamientos estéticos y de recuperación.',
                ],
                'image' => 'wp:'.self::WP.'FOTOREJUVENECIMIENTO-destacada.webp',
                'description_source' => 'redactada',
            ],
            [
                'title' => 'Cámara hiperbárica — sesión',
                'source_name' => 'CÁMARA HIPERBÁRICA',
                'category' => 'bienestar-y-recuperacion',
                'price_cop' => 150000,
                'short_description' => 'Oxigenación hiperbárica para acelerar la recuperación de los tejidos y desinflamar.',
                'paragraphs' => [
                    'En la cámara hiperbárica se respira oxígeno a una presión mayor que la atmosférica, lo que aumenta la cantidad de oxígeno que llega a los tejidos.',
                    'Es un complemento de los programas de recuperación posoperatoria de H&L: favorece la cicatrización, ayuda a reducir la inflamación y acelera la recuperación.',
                ],
                'benefits' => [
                    'Favorece la cicatrización.',
                    'Ayuda a reducir la inflamación.',
                    'Acelera la recuperación.',
                ],
                'image' => 'file:camara-hiperbarica.png',
                'description_source' => 'redactada',
            ],
            [
                'title' => 'Fototerapia — sesión',
                'source_name' => 'FOTOTERAPIA SESIÓN',
                'category' => 'bienestar-y-recuperacion',
                'price_cop' => 88000,
                'short_description' => 'Terapia de luz LED que estimula la regeneración de los tejidos y la producción de colágeno.',
                'paragraphs' => [
                    'La fototerapia utiliza luz LED de longitudes de onda específicas para estimular la regeneración de tejidos internos y externos.',
                    'Favorece la cicatrización y la producción de colágeno, y es parte del protocolo de recuperación posoperatoria de H&L.',
                ],
                'benefits' => [
                    'Estimula la regeneración de los tejidos.',
                    'Favorece la cicatrización.',
                    'Estimula la producción de colágeno.',
                ],
                'image' => 'file:fototerapia.png',
            ],
        ];
    }

    public static function bodyHtml(array $item): string
    {
        $html = collect($item['paragraphs'])->map(fn ($p) => '<p>'.e($p).'</p>')->implode("\n");

        if (! empty($item['benefits'])) {
            $html .= "\n<h3>Beneficios</h3>\n<ul>\n"
                .collect($item['benefits'])->map(fn ($b) => '<li>'.e($b).'</li>')->implode("\n")
                ."\n</ul>";
        }

        return $html;
    }
}
