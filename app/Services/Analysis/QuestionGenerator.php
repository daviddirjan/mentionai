<?php

namespace App\Services\Analysis;

use App\Models\Analysis;
use App\Models\AnalysisQuestion;
use App\Models\PromptTemplate;
use App\Services\Detection\TextNormalizer;
use Illuminate\Support\Collection;
use Locale;

/**
 * Deterministic discovery-question generation. No LLM calls.
 * English templates are used as written; other languages use a fixed phrase table keyed by template intent.
 * Product rule: a question never contains the company name, a brand variation or the domain.
 */
class QuestionGenerator
{
    private const GENERIC = [
        'en' => 'businesses', 'ro' => 'companii', 'es' => 'empresas', 'fr' => 'entreprises', 'de' => 'Unternehmen',
        'it' => 'aziende', 'pt' => 'empresas', 'nl' => 'bedrijven', 'pl' => 'firmy',
    ];

    /**
     * Native phrasings keyed by the English seeder template (PromptTemplateSeeder::TEMPLATES); English uses the
     * template as written. An admin-edited template without an entry falls back to its base intent (intent()).
     */
    private const PHRASES = [
        'Recommend the best {industry} companies in {market}.' => [
            'ro' => 'Recomandă cele mai bune companii de {industry} din {market}.',
            'de' => 'Empfiehl die besten Unternehmen für {industry} in {market}.',
            'es' => 'Recomienda las mejores empresas de {industry} en {market}.',
            'fr' => 'Recommande les meilleures entreprises de {industry} en {market}.',
            'it' => 'Consiglia le migliori aziende di {industry} in {market}.',
            'pt' => 'Recomenda as melhores empresas de {industry} em {market}.',
            'nl' => 'Beveel de beste bedrijven in {industry} in {market} aan.',
            'pl' => 'Poleć najlepsze firmy z branży {industry} w {market}.',
        ],
        'Which companies offer {service} in {market}? I am looking for a reliable provider.' => [
            'ro' => 'Ce companii oferă {service} în {market}? Caut un furnizor de încredere.',
            'de' => 'Welche Unternehmen bieten {service} in {market} an? Ich suche einen zuverlässigen Anbieter.',
            'es' => '¿Qué empresas ofrecen {service} en {market}? Busco un proveedor fiable.',
            'fr' => 'Quelles entreprises proposent {service} en {market} ? Je cherche un prestataire fiable.',
            'it' => 'Quali aziende offrono {service} in {market}? Cerco un fornitore affidabile.',
            'pt' => 'Que empresas oferecem {service} em {market}? Procuro um fornecedor de confiança.',
            'nl' => 'Welke bedrijven bieden {service} aan in {market}? Ik zoek een betrouwbare leverancier.',
            'pl' => 'Które firmy oferują {service} w {market}? Szukam rzetelnego dostawcy.',
        ],
        'I need a trustworthy {industry} provider in {city}. Which companies would you suggest?' => [
            'ro' => 'Am nevoie de un furnizor de {industry} de încredere în {city}. Ce companii mi-ai sugera?',
            'de' => 'Ich brauche einen vertrauenswürdigen Anbieter für {industry} in {city}. Welche Unternehmen würdest du vorschlagen?',
            'es' => 'Necesito un proveedor de {industry} de confianza en {city}. ¿Qué empresas me sugieres?',
            'fr' => 'J\'ai besoin d\'un prestataire de {industry} de confiance à {city}. Quelles entreprises me suggères-tu ?',
            'it' => 'Ho bisogno di un fornitore di {industry} affidabile a {city}. Quali aziende mi suggerisci?',
            'pt' => 'Preciso de um fornecedor de {industry} de confiança em {city}. Que empresas sugeres?',
            'nl' => 'Ik zoek een betrouwbare aanbieder van {industry} in {city}. Welke bedrijven raad je aan?',
            'pl' => 'Potrzebuję godnego zaufania dostawcy usług {industry} w {city}. Jakie firmy mi polecisz?',
        ],
        'What are the most reputable {industry} businesses in {market} and what are they known for?' => [
            'ro' => 'Care sunt cele mai respectate companii de {industry} din {market} și cu ce se remarcă?',
            'de' => 'Welche sind die angesehensten Unternehmen für {industry} in {market} und wofür sind sie bekannt?',
            'es' => '¿Cuáles son las empresas de {industry} más reputadas de {market} y por qué destacan?',
            'fr' => 'Quelles sont les entreprises de {industry} les plus réputées en {market} et pour quoi se distinguent-elles ?',
            'it' => 'Quali sono le aziende di {industry} più rispettate in {market} e per cosa sono conosciute?',
            'pt' => 'Quais são as empresas de {industry} mais conceituadas em {market} e pelo que são conhecidas?',
            'nl' => 'Wat zijn de meest gerenommeerde bedrijven in {industry} in {market} en waar staan ze om bekend?',
            'pl' => 'Jakie są najbardziej renomowane firmy z branży {industry} w {market} i z czego słyną?',
        ],
        'Which are the top 5 {industry} companies in {market} right now?' => [
            'ro' => 'Care sunt în prezent primele 5 companii de {industry} din {market}?',
            'de' => 'Welche sind derzeit die Top 5 Unternehmen für {industry} in {market}?',
            'es' => '¿Cuáles son ahora mismo las 5 mejores empresas de {industry} en {market}?',
            'fr' => 'Quelles sont actuellement les 5 meilleures entreprises de {industry} en {market} ?',
            'it' => 'Quali sono in questo momento le 5 migliori aziende di {industry} in {market}?',
            'pt' => 'Quais são, neste momento, as 5 melhores empresas de {industry} em {market}?',
            'nl' => 'Wat zijn op dit moment de top 5 bedrijven in {industry} in {market}?',
            'pl' => 'Jakie jest obecnie 5 najlepszych firm z branży {industry} w {market}?',
        ],
        'Who are the leading {industry} providers in {market}?' => [
            'ro' => 'Care sunt principalii furnizori de {industry} din {market}?',
            'de' => 'Wer sind die führenden Anbieter für {industry} in {market}?',
            'es' => '¿Quiénes son los principales proveedores de {industry} en {market}?',
            'fr' => 'Quels sont les principaux prestataires de {industry} en {market} ?',
            'it' => 'Quali sono i principali fornitori di {industry} in {market}?',
            'pt' => 'Quem são os principais fornecedores de {industry} em {market}?',
            'nl' => 'Wie zijn de toonaangevende aanbieders van {industry} in {market}?',
            'pl' => 'Kim są wiodący dostawcy z branży {industry} w {market}?',
        ],
        'If you had to pick one {industry} company in {city}, which would it be and why?' => [
            'ro' => 'Dacă ar trebui să alegi o singură companie de {industry} din {city}, care ar fi și de ce?',
            'de' => 'Wenn du ein einziges Unternehmen für {industry} in {city} auswählen müsstest, welches wäre es und warum?',
            'es' => 'Si tuvieras que elegir una sola empresa de {industry} en {city}, ¿cuál sería y por qué?',
            'fr' => 'Si tu devais choisir une seule entreprise de {industry} à {city}, laquelle serait-ce et pourquoi ?',
            'it' => 'Se dovessi scegliere una sola azienda di {industry} a {city}, quale sarebbe e perché?',
            'pt' => 'Se tivesses de escolher uma única empresa de {industry} em {city}, qual seria e porquê?',
            'nl' => 'Als je één bedrijf in {industry} in {city} moest kiezen, welk zou het zijn en waarom?',
            'pl' => 'Gdybyś miał wybrać jedną firmę z branży {industry} w {city}, która by to była i dlaczego?',
        ],
        'I need {service}. Which companies in {market} do you recommend?' => [
            'ro' => 'Am nevoie de {service}. Ce companii din {market} îmi recomanzi?',
            'de' => 'Ich brauche {service}. Welche Unternehmen in {market} empfiehlst du?',
            'es' => 'Necesito {service}. ¿Qué empresas de {market} me recomiendas?',
            'fr' => 'J\'ai besoin de {service}. Quelles entreprises en {market} me recommandes-tu ?',
            'it' => 'Ho bisogno di {service}. Quali aziende in {market} mi consigli?',
            'pt' => 'Preciso de {service}. Que empresas em {market} recomendas?',
            'nl' => 'Ik heb {service} nodig. Welke bedrijven in {market} raad je aan?',
            'pl' => 'Potrzebuję usługi: {service}. Jakie firmy w {market} polecasz?',
        ],
        'Which companies are best for {service} in {city}?' => [
            'ro' => 'Care sunt cele mai bune companii pentru {service} în {city}?',
            'de' => 'Welche Unternehmen sind am besten für {service} in {city}?',
            'es' => '¿Qué empresas son las mejores para {service} en {city}?',
            'fr' => 'Quelles entreprises sont les meilleures pour {service} à {city} ?',
            'it' => 'Quali sono le aziende migliori per {service} a {city}?',
            'pt' => 'Quais são as melhores empresas para {service} em {city}?',
            'nl' => 'Welke bedrijven zijn het beste voor {service} in {city}?',
            'pl' => 'Które firmy są najlepsze, jeśli chodzi o {service}, w {city}?',
        ],
        'Which {industry} companies are based in {city}?' => [
            'ro' => 'Ce companii de {industry} au sediul în {city}?',
            'de' => 'Welche Unternehmen für {industry} haben ihren Sitz in {city}?',
            'es' => '¿Qué empresas de {industry} tienen su sede en {city}?',
            'fr' => 'Quelles entreprises de {industry} sont basées à {city} ?',
            'it' => 'Quali aziende di {industry} hanno sede a {city}?',
            'pt' => 'Que empresas de {industry} estão sediadas em {city}?',
            'nl' => 'Welke bedrijven in {industry} zijn gevestigd in {city}?',
            'pl' => 'Jakie firmy z branży {industry} mają siedzibę w {city}?',
        ],
        'I run a business in {city}. Who should I hire for {service}?' => [
            'ro' => 'Am o afacere în {city}. Pe cine ar trebui să angajez pentru {service}?',
            'de' => 'Ich führe ein Unternehmen in {city}. Wen sollte ich für {service} beauftragen?',
            'es' => 'Tengo un negocio en {city}. ¿A quién debería contratar para {service}?',
            'fr' => 'Je dirige une entreprise à {city}. Qui devrais-je engager pour {service} ?',
            'it' => 'Ho un\'attività a {city}. A chi dovrei affidarmi per {service}?',
            'pt' => 'Tenho um negócio em {city}. Quem devo contratar para {service}?',
            'nl' => 'Ik heb een bedrijf in {city}. Wie moet ik inschakelen voor {service}?',
            'pl' => 'Prowadzę firmę w {city}. Kogo warto zatrudnić, jeśli chodzi o {service}?',
        ],
        'Recommend {industry} companies near {city}.' => [
            'ro' => 'Recomandă companii de {industry} din apropiere de {city}.',
            'de' => 'Empfiehl Unternehmen für {industry} in der Nähe von {city}.',
            'es' => 'Recomienda empresas de {industry} cerca de {city}.',
            'fr' => 'Recommande des entreprises de {industry} près de {city}.',
            'it' => 'Consiglia aziende di {industry} vicino a {city}.',
            'pt' => 'Recomenda empresas de {industry} perto de {city}.',
            'nl' => 'Beveel bedrijven in {industry} in de buurt van {city} aan.',
            'pl' => 'Poleć firmy z branży {industry} w pobliżu miejscowości {city}.',
        ],
        'I have a project that needs {service}. Which reliable companies in {market} could handle it?' => [
            'ro' => 'Am un proiect care necesită {service}. Ce companii de încredere din {market} s-ar putea ocupa de el?',
            'de' => 'Ich habe ein Projekt, für das ich {service} brauche. Welche zuverlässigen Unternehmen in {market} könnten es übernehmen?',
            'es' => 'Tengo un proyecto que necesita {service}. ¿Qué empresas fiables de {market} podrían encargarse de él?',
            'fr' => 'J\'ai un projet qui nécessite {service}. Quelles entreprises fiables en {market} pourraient s\'en charger ?',
            'it' => 'Ho un progetto che richiede {service}. Quali aziende affidabili in {market} potrebbero occuparsene?',
            'pt' => 'Tenho um projeto que precisa de {service}. Que empresas de confiança em {market} poderiam tratar dele?',
            'nl' => 'Ik heb een project waarvoor {service} nodig is. Welke betrouwbare bedrijven in {market} kunnen dat oppakken?',
            'pl' => 'Mam projekt, który wymaga usługi: {service}. Które rzetelne firmy w {market} mogłyby się nim zająć?',
        ],
        'Which {industry} companies in {market} are known for delivering on time and on budget?' => [
            'ro' => 'Ce companii de {industry} din {market} sunt cunoscute pentru livrarea la timp și în limita bugetului?',
            'de' => 'Welche Unternehmen für {industry} in {market} sind dafür bekannt, termingerecht und im Budget zu liefern?',
            'es' => '¿Qué empresas de {industry} en {market} son conocidas por entregar a tiempo y dentro del presupuesto?',
            'fr' => 'Quelles entreprises de {industry} en {market} sont réputées pour livrer dans les délais et dans le budget ?',
            'it' => 'Quali aziende di {industry} in {market} sono note per consegnare nei tempi e nel budget?',
            'pt' => 'Que empresas de {industry} em {market} são conhecidas por cumprir prazos e orçamentos?',
            'nl' => 'Welke bedrijven in {industry} in {market} staan erom bekend dat ze op tijd en binnen budget leveren?',
            'pl' => 'Które firmy z branży {industry} w {market} słyną z realizacji projektów na czas i w budżecie?',
        ],
        'Which affordable {industry} companies in {market} offer good value for money?' => [
            'ro' => 'Ce companii de {industry} accesibile din {market} oferă un raport bun calitate-preț?',
            'de' => 'Welche günstigen Unternehmen für {industry} in {market} bieten ein gutes Preis-Leistungs-Verhältnis?',
            'es' => '¿Qué empresas de {industry} asequibles en {market} ofrecen una buena relación calidad-precio?',
            'fr' => 'Quelles entreprises de {industry} abordables en {market} offrent un bon rapport qualité-prix ?',
            'it' => 'Quali aziende di {industry} convenienti in {market} offrono un buon rapporto qualità-prezzo?',
            'pt' => 'Que empresas de {industry} acessíveis em {market} oferecem uma boa relação qualidade-preço?',
            'nl' => 'Welke betaalbare bedrijven in {industry} in {market} bieden goede waar voor hun geld?',
            'pl' => 'Które niedrogie firmy z branży {industry} w {market} oferują dobry stosunek jakości do ceny?',
        ],
        'I have a limited budget for {service}. Which providers in {market} would you recommend?' => [
            'ro' => 'Am un buget limitat pentru {service}. Ce furnizori din {market} mi-ai recomanda?',
            'de' => 'Ich habe ein begrenztes Budget für {service}. Welche Anbieter in {market} würdest du empfehlen?',
            'es' => 'Tengo un presupuesto limitado para {service}. ¿Qué proveedores de {market} me recomendarías?',
            'fr' => 'J\'ai un budget limité pour {service}. Quels prestataires en {market} me recommanderais-tu ?',
            'it' => 'Ho un budget limitato per {service}. Quali fornitori in {market} mi consiglieresti?',
            'pt' => 'Tenho um orçamento limitado para {service}. Que fornecedores em {market} recomendarias?',
            'nl' => 'Ik heb een beperkt budget voor {service}. Welke aanbieders in {market} zou je aanraden?',
            'pl' => 'Mam ograniczony budżet na usługę: {service}. Których dostawców w {market} byś polecił?',
        ],
        'What are good alternatives to the big {industry} companies in {market}?' => [
            'ro' => 'Care sunt alternative bune la marile companii de {industry} din {market}?',
            'de' => 'Was sind gute Alternativen zu den großen Unternehmen für {industry} in {market}?',
            'es' => '¿Cuáles son buenas alternativas a las grandes empresas de {industry} en {market}?',
            'fr' => 'Quelles sont les bonnes alternatives aux grandes entreprises de {industry} en {market} ?',
            'it' => 'Quali sono delle buone alternative alle grandi aziende di {industry} in {market}?',
            'pt' => 'Quais são boas alternativas às grandes empresas de {industry} em {market}?',
            'nl' => 'Wat zijn goede alternatieven voor de grote bedrijven in {industry} in {market}?',
            'pl' => 'Jakie są dobre alternatywy dla dużych firm z branży {industry} w {market}?',
        ],
        'Besides the best-known names, which smaller {industry} companies in {market} are worth considering?' => [
            'ro' => 'În afară de numele cele mai cunoscute, ce companii mai mici de {industry} din {market} merită luate în considerare?',
            'de' => 'Welche kleineren Unternehmen für {industry} in {market} sind neben den bekanntesten Namen eine Überlegung wert?',
            'es' => 'Además de los nombres más conocidos, ¿qué empresas más pequeñas de {industry} en {market} vale la pena considerar?',
            'fr' => 'En dehors des noms les plus connus, quelles petites entreprises de {industry} en {market} méritent d\'être considérées ?',
            'it' => 'Oltre ai nomi più noti, quali aziende di {industry} più piccole in {market} vale la pena considerare?',
            'pt' => 'Para além dos nomes mais conhecidos, que empresas de {industry} mais pequenas em {market} vale a pena considerar?',
            'nl' => 'Welke kleinere bedrijven in {industry} in {market} zijn naast de bekendste namen het overwegen waard?',
            'pl' => 'Które mniejsze firmy z branży {industry} w {market}, poza najbardziej znanymi markami, warto rozważyć?',
        ],
        'Which {industry} companies in {market} have the best client reviews?' => [
            'ro' => 'Ce companii de {industry} din {market} au cele mai bune recenzii de la clienți?',
            'de' => 'Welche Unternehmen für {industry} in {market} haben die besten Kundenbewertungen?',
            'es' => '¿Qué empresas de {industry} en {market} tienen las mejores opiniones de clientes?',
            'fr' => 'Quelles entreprises de {industry} en {market} ont les meilleurs avis clients ?',
            'it' => 'Quali aziende di {industry} in {market} hanno le migliori recensioni dei clienti?',
            'pt' => 'Que empresas de {industry} em {market} têm as melhores avaliações de clientes?',
            'nl' => 'Welke bedrijven in {industry} in {market} hebben de beste klantbeoordelingen?',
            'pl' => 'Które firmy z branży {industry} w {market} mają najlepsze opinie klientów?',
        ],
        'Which {industry} companies in {city} are most trusted by their clients?' => [
            'ro' => 'Ce companii de {industry} din {city} se bucură de cea mai mare încredere din partea clienților?',
            'de' => 'Welchen Unternehmen für {industry} in {city} vertrauen ihre Kunden am meisten?',
            'es' => '¿Qué empresas de {industry} en {city} inspiran más confianza a sus clientes?',
            'fr' => 'Quelles entreprises de {industry} à {city} inspirent le plus confiance à leurs clients ?',
            'it' => 'Di quali aziende di {industry} a {city} si fidano di più i clienti?',
            'pt' => 'Em que empresas de {industry} em {city} os clientes mais confiam?',
            'nl' => 'Welke bedrijven in {industry} in {city} worden het meest vertrouwd door hun klanten?',
            'pl' => 'Którym firmom z branży {industry} w {city} klienci ufają najbardziej?',
        ],
        'Which companies in {market} specialize in {service}?' => [
            'ro' => 'Ce companii din {market} sunt specializate în {service}?',
            'de' => 'Welche Unternehmen in {market} sind auf {service} spezialisiert?',
            'es' => '¿Qué empresas de {market} están especializadas en {service}?',
            'fr' => 'Quelles entreprises en {market} sont spécialisées dans {service} ?',
            'it' => 'Quali aziende in {market} sono specializzate in {service}?',
            'pt' => 'Que empresas em {market} são especializadas em {service}?',
            'nl' => 'Welke bedrijven in {market} zijn gespecialiseerd in {service}?',
            'pl' => 'Które firmy w {market} specjalizują się w obszarze: {service}?',
        ],
        'Who are the experts in {service} in {city}?' => [
            'ro' => 'Cine sunt experții în {service} din {city}?',
            'de' => 'Wer sind die Experten für {service} in {city}?',
            'es' => '¿Quiénes son los expertos en {service} en {city}?',
            'fr' => 'Qui sont les experts en {service} à {city} ?',
            'it' => 'Chi sono gli esperti di {service} a {city}?',
            'pt' => 'Quem são os especialistas em {service} em {city}?',
            'nl' => 'Wie zijn de experts in {service} in {city}?',
            'pl' => 'Kim są eksperci w {city} w obszarze: {service}?',
        ],
        'We are a mid-sized company in {market} looking for a long-term {industry} partner. Who would you recommend?' => [
            'ro' => 'Suntem o companie de dimensiune medie din {market} și căutăm un partener de {industry} pe termen lung. Pe cine ne-ai recomanda?',
            'de' => 'Wir sind ein mittelständisches Unternehmen in {market} und suchen einen langfristigen Partner für {industry}. Wen würdest du empfehlen?',
            'es' => 'Somos una empresa mediana de {market} y buscamos un socio de {industry} a largo plazo. ¿A quién nos recomendarías?',
            'fr' => 'Nous sommes une entreprise de taille moyenne en {market} et cherchons un partenaire de {industry} sur le long terme. Qui nous recommanderais-tu ?',
            'it' => 'Siamo un\'azienda di medie dimensioni in {market} e cerchiamo un partner di {industry} a lungo termine. Chi ci consiglieresti?',
            'pt' => 'Somos uma empresa de média dimensão em {market} à procura de um parceiro de {industry} a longo prazo. Quem nos recomendarias?',
            'nl' => 'Wij zijn een middelgroot bedrijf in {market} en zoeken een langetermijnpartner voor {industry}. Wie zou je aanraden?',
            'pl' => 'Jesteśmy średniej wielkości firmą w {market} i szukamy długoterminowego partnera z branży {industry}. Kogo byś polecił?',
        ],
        'Which {industry} companies in {market} work with startups and SMEs?' => [
            'ro' => 'Ce companii de {industry} din {market} lucrează cu startup-uri și IMM-uri?',
            'de' => 'Welche Unternehmen für {industry} in {market} arbeiten mit Start-ups und KMU?',
            'es' => '¿Qué empresas de {industry} en {market} trabajan con startups y pymes?',
            'fr' => 'Quelles entreprises de {industry} en {market} travaillent avec des startups et des PME ?',
            'it' => 'Quali aziende di {industry} in {market} lavorano con startup e PMI?',
            'pt' => 'Que empresas de {industry} em {market} trabalham com startups e PME?',
            'nl' => 'Welke bedrijven in {industry} in {market} werken met startups en mkb-bedrijven?',
            'pl' => 'Które firmy z branży {industry} w {market} współpracują ze startupami i MŚP?',
        ],
        'Which {industry} companies in {market} have experience with enterprise clients?' => [
            'ro' => 'Ce companii de {industry} din {market} au experiență cu clienți corporativi mari?',
            'de' => 'Welche Unternehmen für {industry} in {market} haben Erfahrung mit Großkunden?',
            'es' => '¿Qué empresas de {industry} en {market} tienen experiencia con grandes empresas como clientes?',
            'fr' => 'Quelles entreprises de {industry} en {market} ont de l\'expérience avec les grands comptes ?',
            'it' => 'Quali aziende di {industry} in {market} hanno esperienza con grandi clienti aziendali?',
            'pt' => 'Que empresas de {industry} em {market} têm experiência com grandes clientes empresariais?',
            'nl' => 'Welke bedrijven in {industry} in {market} hebben ervaring met grote zakelijke klanten?',
            'pl' => 'Które firmy z branży {industry} w {market} mają doświadczenie z klientami korporacyjnymi?',
        ],
    ];

    private const FALLBACK_TEMPLATE = 'Recommend the best {industry} companies in {market}.';

    /** @return Collection<int, AnalysisQuestion> */
    public function generate(Analysis $analysis): Collection
    {
        $existing = $analysis->questions()->get();
        if ($existing->isNotEmpty()) {
            return $existing;
        }

        $company = $analysis->company()->first();
        $terms = self::brandTerms($analysis->company_name, $analysis->domain, $company?->brand_variations ?? []);
        $inspection = $analysis->website_inspection ?? [];
        $audit = $analysis->isAudit();
        $count = max(1, (int) settings($audit ? 'audit_query_count' : 'query_count'));

        // The free check never uses audit-only templates; an audit uses every enabled template.
        $templates = PromptTemplate::enabled()->when(! $audit, fn ($q) => $q->where('is_audit_only', false))->get();
        if ($templates->isEmpty()) {
            $templates = collect([new PromptTemplate(['name' => 'Default', 'template' => self::FALLBACK_TEMPLATE])]);
        }

        $questions = collect();
        $usedIds = [];
        $seen = [];

        // Audits cycle the templates when there are fewer than $count; later passes shift the {service} offset,
        // and a pass that yields no new (deduplicated) question ends the loop.
        $passes = $audit ? 5 : 1;
        for ($pass = 0; $pass < $passes && $questions->count() < $count; $pass++) {
            $before = $questions->count();
            foreach ($templates as $template) {
                if ($questions->count() >= $count) {
                    break;
                }
                $text = self::build($template->template, $analysis->language, $analysis->market, $analysis->industry, $inspection, $questions->count() + $pass, $terms, $analysis->location);
                if (isset($seen[mb_strtolower($text)])) {
                    continue;
                }
                $seen[mb_strtolower($text)] = true;

                $questions->push($analysis->questions()->create([
                    'prompt_template_id' => $template->exists ? $template->id : null,
                    'position' => $questions->count() + 1,
                    'question' => $text,
                    'template_snapshot' => $template->template,
                ]));
                if ($template->exists) {
                    $usedIds[$template->id] = $template->id;
                }
            }
            if ($questions->count() === $before) {
                break;
            }
        }

        $analysis->update(['query_config' => [
            'query_count' => $count,
            'generated_count' => $questions->count(),
            'template_ids' => array_values($usedIds),
        ] + ($audit ? ['tier' => 'audit'] : [])]);

        return $questions;
    }

    /**
     * @param  array<string, mixed>  $inspection
     * @param  list<string>  $terms
     * @param  ?string  $location  visitor-chosen city / county / region; fills {city} ahead of the scraped city
     */
    public static function build(string $template, string $language, string $market, ?string $industry, array $inspection, int $offset, array $terms, ?string $location = null): string
    {
        $language = strtolower($language);
        $pattern = $language === 'en' || ! isset(self::PHRASES[self::FALLBACK_TEMPLATE][$language])
            ? $template
            : (self::PHRASES[trim($template)][$language] ?? self::PHRASES[self::intent($template)][$language]);

        $generic = self::GENERIC[$language] ?? self::GENERIC['en'];
        $country = self::countryName($market, $language);

        $values = [
            // Without an industry, a scraped service reads better than the generic word ("companii de companii").
            'industry' => self::safe($industry, $terms) ?? self::scrapedService($inspection, 0, $terms) ?? $generic,
            'market' => $country,
            'language' => Locale::getDisplayLanguage($language, $language) ?: $language,
        ];
        $values['service'] = self::safe($industry, $terms) ?? self::scrapedService($inspection, $offset, $terms) ?? $values['industry'];
        $values['city'] = self::place($location, $country, $terms) ?? self::safe($inspection['city'] ?? null, $terms) ?? $country;

        $question = self::fill($pattern, $values);
        if ($values['industry'] === $generic) {
            // e.g. RO "companii de companii", FR "entreprises de entreprises" → keep one word.
            $question = preg_replace('/\b('.preg_quote($generic, '/').')\s+(?:de|d’|di|van|von|of)\s+\1\b/u', '$1', $question);
            $question = preg_replace('/\bbusinesses (companies|businesses|providers)\b/u', '$1', $question);
        }

        if (self::leaks($question, $terms)) {
            $question = self::strip($question, $terms);
        }
        if (self::leaks($question, $terms) || trim($question) === '') {
            $fallback = $language === 'en' ? self::FALLBACK_TEMPLATE : self::PHRASES[self::FALLBACK_TEMPLATE][$language];
            $question = self::fill($fallback, ['industry' => $generic, 'market' => $country]);
        }

        return $question;
    }

    /** @param  list<string>  $variations @return list<string> */
    public static function brandTerms(string $name, string $domain, array $variations = []): array
    {
        $domain = strtolower(preg_replace('/^www\./', '', $domain));
        $label = explode('.', $domain)[0] ?? '';
        $candidates = [$name, TextNormalizer::brandName($name), $domain, 'www.'.$domain, $label, ...array_filter($variations, 'is_string')];

        $terms = [];
        foreach ($candidates as $candidate) {
            $folded = trim(TextNormalizer::fold($candidate));
            if (mb_strlen($folded) >= 3) {
                $terms[$folded] = $folded;
            }
        }
        uasort($terms, fn ($a, $b) => mb_strlen($b) <=> mb_strlen($a));

        return array_values($terms);
    }

    /** @param  list<string>  $terms folded brand terms from brandTerms() */
    /**
     * Scraped headings/nav links are noisy ("tools", "works") and include the company's own product
     * names, which would identify the brand. Only multi-word phrases sharing no stem with the brand qualify.
     *
     * @param  list<string>  $terms
     */
    private static function scrapedService(array $inspection, int $offset, array $terms): ?string
    {
        $stems = array_filter(array_map(fn ($t) => mb_substr(TextNormalizer::fold($t), 0, 5), $terms), fn ($s) => mb_strlen($s) >= 4);

        $services = array_values(array_filter(
            array_map(fn ($s) => is_string($s) ? self::safe($s, $terms) : null, $inspection['services'] ?? []),
            function (?string $service) use ($stems): bool {
                if ($service === null || count(preg_split('/\s+/u', $service)) < 2) {
                    return false;
                }
                foreach (preg_split('/[^\p{L}\p{N}]+/u', TextNormalizer::fold($service), -1, PREG_SPLIT_NO_EMPTY) as $word) {
                    foreach ($stems as $stem) {
                        if (str_starts_with($word, $stem)) {
                            return false;
                        }
                    }
                }

                return true;
            },
        ));

        return $services === [] ? null : $services[$offset % count($services)];
    }

    public static function leaks(string $text, array $terms): bool
    {
        $folded = TextNormalizer::fold($text);
        foreach ($terms as $term) {
            if (preg_match(TextNormalizer::boundaryPattern($term), $folded)) {
                return true;
            }
        }

        return false;
    }

    /** @param  list<string>  $terms */
    public static function strip(string $text, array $terms): string
    {
        $words = preg_split('/\s+/u', trim($text), -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $core = fn (string $w): string => TextNormalizer::fold(preg_replace('/^[^\p{L}\p{N}]+|[^\p{L}\p{N}]+$/u', '', $w));

        foreach ($terms as $term) {
            $termWords = explode(' ', $term);
            $n = count($termWords);
            for ($i = 0; $i + $n <= count($words); $i++) {
                $slice = array_map($core, array_slice($words, $i, $n));
                if ($slice === $termWords) {
                    $tail = preg_match('/[?!.,;:]+$/u', $words[$i + $n - 1], $m) ? $m[0] : '';
                    array_splice($words, $i, $n);
                    if ($tail !== '' && $i > 0) {
                        $words[$i - 1] = rtrim($words[$i - 1], '?!.,;:').$tail;
                    }
                    $i--;
                }
            }
        }

        return trim(implode(' ', $words));
    }

    /**
     * "Cluj" alone is ambiguous to a search engine, so the country is appended unless the visitor already wrote it.
     *
     * @param  list<string>  $terms
     */
    private static function place(?string $location, string $country, array $terms): ?string
    {
        $location = self::safe($location, $terms);
        if ($location === null) {
            return null;
        }

        return str_contains(TextNormalizer::fold($location), TextNormalizer::fold($country)) ? $location : "{$location}, {$country}";
    }

    private static function safe(?string $value, array $terms): ?string
    {
        $value = trim(preg_replace('/\s+/u', ' ', (string) $value));
        if ($value === '') {
            return null;
        }
        $value = mb_substr($value, 0, 80);
        if (self::leaks($value, $terms)) {
            $value = trim(self::strip($value, $terms), " \t-–—,;:|");
            if ($value === '' || self::leaks($value, $terms)) {
                return null;
            }
        }

        return $value;
    }

    /** @param  array<string, string>  $values */
    private static function fill(string $pattern, array $values): string
    {
        $out = preg_replace_callback('/\{(industry|service|market|city|language)\}/', fn ($m) => $values[$m[1]] ?? '', $pattern);

        return trim(preg_replace('/\s+/u', ' ', $out));
    }

    /** The base template whose phrasings an admin-edited (unknown) template borrows in other languages. */
    private static function intent(string $template): string
    {
        return match (true) {
            str_contains($template, '{service}') => 'Which companies offer {service} in {market}? I am looking for a reliable provider.',
            str_contains($template, '{city}') => 'I need a trustworthy {industry} provider in {city}. Which companies would you suggest?',
            (bool) preg_match('/reputab|known for|compar/i', $template) => 'What are the most reputable {industry} businesses in {market} and what are they known for?',
            default => self::FALLBACK_TEMPLATE,
        };
    }

    private static function countryName(string $market, string $language): string
    {
        $market = strtoupper($market);
        $name = Locale::getDisplayRegion('-'.$market, $language);

        return $name !== '' && $name !== $market ? $name : $market;
    }
}
