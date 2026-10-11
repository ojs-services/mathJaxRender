# MathJax for OJS (mathJaxRender)

[English](README.md)

LaTeX/TeX matematiğini OJS dergi sayfalarında [MathJax](https://www.mathjax.org/) 3.2.2 ile görüntüler.
**OJS Services** tarafından geliştirilir ve bakımı yapılır · GPL v3

## Özellikler

- Ön yüzde satır içi `\( ... \)` ve blok `$$ ... $$` / `\[ ... \]` TeX formüllerini görüntüler.
- **Granüler sayfa seçimi** — MathJax yalnızca seçtiğiniz sayfalarda yüklenir (makale
  sayfası, HTML galley, sayı içindekiler/arşiv, arama sonuçları, ana sayfa/duyurular).
  Seçilmeyen sayfalara hiç dokunulmaz: script yok, yük yok.
- **İki kaynak**: eklentiyle birlikte gelen tam self-host kopya (varsayılan; dış istek yok —
  gizlilik/KVKK hassasiyeti olan ve intranet kurulumları için uygun) veya jsDelivr CDN
  (sabit sürüm).
- **HTML galley tam metin render'ı.** OJS, HTML galley'i iframe içinde ayrı bir doküman olarak
  sunar; sayfa başlığı oraya asla erişemez — bu eklenti MathJax'i o iç dokümanın kendisine
  enjekte eder.
- **Editör arayüzü (backend) render'ı — deneysel, varsayılan kapalı.** Gönderi başlıkları gibi
  editör listelerindeki formülleri görüntüler. OJS backend'i, listeleri sayfa yüklendikten sonra
  getiren bir Vue tek-sayfa uygulamasıdır; bu yüzden eklenti yeni içeriği yeniden dizen bir
  mutation observer ile gelir. Editoryal akışı etkilerse ayarlardan kapatmanız yeterli.
- İsteğe bağlı tek dolar `$ ... $` satır içi sınırlayıcı (ön yüzde varsayılan kapalı; böylece
  "fiyatlar $5 ile $10 arasında" gibi metinler asla bozulmaz — backend'de ise başlıklar
  genelde öyle yazıldığı için varsayılan açık).
- İsteğe bağlı MathML ve AsciiMath girdi desteği.
- **Güvenli kip.** Başlık, öz ve kaynakçayı yazarlar girdiği için MathJax güvenli kipinde çalışır:
  formüllerde yazılan bağlantılar (`\href`) yalnız http ve https olabilir; sınıf, kimlik
  ve stil makroları kısıtlıdır. Sıradan formüller etkilenmez.
- **Sonradan eklenen içerik.** Sayfa yüklendikten sonra eklenen içerik — örneğin okuyucu
  *Nasıl atıf yapılır* kutusunda atıf biçimini değiştirdiğinde yüklenen biçimler — otomatik
  olarak dizilir.
- Dergi bazlı ayarlar; çok dergili kurulumlarda her dergi bağımsız yapılandırılır.

## Ekran görüntüleri

Tüm görüntüler demo verisiyle alınmıştır. Varsayılan tema OJS 3.4 üzerinde, Atlas teması OJS 3.5 üzerinde gösterilmiştir.

| | Varsayılan tema | Atlas teması |
|---|---|---|
| Başlık ve öz | ![Başlık ve özdeki formüller, varsayılan tema](docs/default-title-abstract.png) | ![Başlık ve özdeki formüller, Atlas teması](docs/atlas-title-abstract.png) |
| Kaynakça | ![Kaynakçadaki formüller, varsayılan tema](docs/default-references.png) | ![Kaynakçadaki formüller, Atlas teması](docs/atlas-references.png) |
| Nasıl atıf yapılır | ![Nasıl atıf yapılır kutusu, varsayılan tema](docs/default-howtocite.png) | ![Nasıl atıf yapılır kutusu, Atlas teması](docs/atlas-howtocite.png) |
| Atıf biçimi değiştirildikten sonra | ![Atıf biçimi değiştirildikten sonra kutu, varsayılan tema](docs/default-howtocite-style.png) | ![Atıf biçimi değiştirildikten sonra kutu, Atlas teması](docs/atlas-howtocite-style.png) |
| HTML tam metin | ![HTML tam metindeki formüller, varsayılan tema](docs/default-fulltext.png) | ![HTML tam metindeki formüller, Atlas teması](docs/atlas-fulltext.png) |

## Gereksinimler

- **PHP:** OJS sürümünüzün gerektirdiği sürüm. Eklentinin denendiği sürümler *Uyumluluk*
  bölümünde listelenir.
- HTML tam metinlerde matematik görüntülenmesini istiyorsanız *HTML Article Galley* eklentisi.

## Uyumluluk

| Eklenti sürümü | OJS, tam test | OJS, otomatik regresyon koşusuyla da denenen | PHP (tam testler) | Paket |
|---|---|---|---|---|
| 1.x (1.1.0.12) | 3.3.0.22 | 3.3.0.16, 3.3.0.20, 3.3.0.21 | 7.4, 8.1 | `mathJaxRender-ojs3.3-1.1.0.12.tar.gz` |
| 2.x (2.0.0.10) | 3.4.0.10, 3.5.0.5 | 3.4.0.3, 3.5.0.1, 3.5.0.3 | 3.4: 8.1, 8.2 — 3.5: 8.2 | `mathJaxRender-ojs3.4-3.5-2.0.0.10.tar.gz` |

"Tam test", formül çiziminin sayfa sayfa denendiği anlamına gelir: makale başlığı, öz, kaynakça,
*Nasıl atıf yapılır* kutusu, sayı ve arama listeleri ve HTML tam metinler. Otomatik regresyon
koşusu, eklentinin makale, sayı, arama ve ana sayfalarında tam bir kez, ayarlanan kaynak ve
sınırlayıcılarla yüklendiğini denetler. Bu OJS hatlarının diğer sürümleri denenmemiştir. İki
eklenti sürümü ayrı kod hatlarıdır: dosya uzantıları, isim alanları ve eklenti yükleyicisi OJS
3.3 ile 3.4+ arasında farklıdır — PKP kendi eklentilerinde de aynı ayrımı yapar. Kaynak depoda
bunlar `ojs-3.3` ve `ojs-3.4-3.5` dallarıdır. Düzeltmeler her ikisine de uygulanır.

## Kurulum

1. MathJax yükleyen başka bir eklenti kuruluysa önce devre dışı bırakın ya da kaldırın: iki eklenti
   MathJax'i iki kez yükler.
2. OJS'te *Web Sitesi Ayarları → Eklentiler → Yeni Eklenti Yükle* ile
   OJS sürümünüze uygun `mathJaxRender-ojs3.3-…` ya da `mathJaxRender-ojs3.4-3.5-…` paketini (bkz. *Uyumluluk*) yükleyin.
3. *Genel Eklentiler* listesinde **MathJax for OJS**'i etkinleştirin.
4. *Ayarlar*'dan etkin alanları, MathJax kaynağını ve sınırlayıcıları seçin.

![Eklenti ayarları](docs/settings.png)

Eklenti yükleme ekranı kaydı otomatik yapar. Dizini elle
kopyalarsanız (örn. git checkout) OJS eklentiyi ancak `versions` tablosuna kayıttan sonra
yükler — yönetici olarak *Web Sitesi Ayarları → Eklentiler* sayfasını bir kez ziyaret
etmek bu kaydı oluşturur.

## Dergide matematik yazımı

- Satır içi: `\(E=mc^2\)`
- Blok: `$$\int_0^1 x\,dx$$` veya `\[ ... \]`
- Tek dolar seçeneği açıkken `$E=mc^2$` de çalışır (ayarlardaki uyarıya bakın).

**Tek dolarla yazılan başlıklar.** Matematik dergileri başlıkları çoğunlukla
`$\mathbb{Z}_n$-simetrik ...` biçiminde yazar. Bunlar editör arayüzünde kutudan çıktığı gibi
render edilir (backend tek-dolar varsayılan açık), ancak okuyucuya dönük sayfalarda *Tek dolar
işaretini de satır içi matematik olarak tanı* seçeneğini açana kadar düz metin olarak kalır.
Derginiz satır içi matematiği böyle yazıyorsa bu seçeneği açın — o durumda gerçek para
tutarlarını `\$5` biçiminde kaçırın; eklentinin yapılandırması bunu düz dolar işareti olarak
korur.

## Dış istekler ve gizlilik

- **Kaynak: eklenti içindeki kopya (varsayılan).** Betik ve yazı tipleri kendi sunucunuzdaki
  eklentinin `mathjax/` dizininden verilir. Eklenti başka hiçbir adrese istek yapmaz; ziyaretçi
  verisi sitenizden dışarı çıkmaz.
- **Kaynak: CDN.** Eklenti ayarlarında *CDN* seçilirse, eklentinin etkin olduğu sayfalarda
  ziyaretçinin tarayıcısı MathJax 3.2.2'yi — betiği ve yazı tipi dosyalarını —
  `cdn.jsdelivr.net` adresinden indirir. Üçüncü tarafça barındırılan her betikte olduğu gibi CDN
  işletmecisi ziyaretçinin IP adresini, tarayıcı bilgisini ve sayfa adresini görür. Eklenti
  OJS Services'e hiçbir şey göndermez.
- Eklentinin etkin olmadığı sayfalar hiçbir MathJax dosyası yüklemez.

## OJS 3.3'ten yükseltirken

İki eklenti sürümü birbirinin yerine kullanılamaz. Bir dergiyi OJS 3.3'ten 3.4 ya da 3.5'e
yükseltirken:

1. OJS'i her zamanki gibi yükseltin.
2. Eski `plugins/generic/mathJaxRender` dizinini (1.x sürümü) silin ve yerine 2.x paketini
   kurun (*Yeni Eklenti Yükle* ya da paketteki dizini kopyalayın). OJS 3.5'te 1.x dizini
   yüklenemez — sunucu hata günlüğüne `Call to undefined function import()` yazılır ve hiçbir
   formül görüntülenmez. OJS 3.4 hâlâ yükler, ancak onu da değiştirmelisiniz.
3. Ayarlarınız korunur: veritabanında aynı eklenti adıyla saklanırlar ve geçişten sonra
   değişmeden kaldılar. Ardından OJS veri önbelleklerini temizleyin.

## Sorun giderme

- **Ayar değişikliğinden veya etkinleştirmeden sonra formüller görünmüyor:** OJS
  önbelleklerini temizleyin (`cache/t_compile/*` ve `cache/fc-pluginSettings-*.php`) veya
  *Yönetim → Veri Önbelleğini Temizle / Şablon Önbelleğini Temizle* kullanın.
- **Formüllerdeki `<` ve `>` kayboluyor:** OJS, `config.inc.php` içindeki `allowed_html`
  ayarıyla kullanıcı girdisini süzer. Bu eklenti çekirdek süzmeyi güvenlik gereği **aşmaz**.
  Formüllerde `<`/`>` gerekiyorsa TeX makrolarını kullanın (`\lt`, `\gt`) ya da
  `allowed_html`'i kendi sorumluluğunuzda genişletin.
- **Kaynakçada ters bölü siliniyor (örn. `\gamma` → `gamma`):** bu OJS çekirdeğinin davranışıdır;
  kaynakçada formül yazımı için *Bilinen sınırlar* bölümüne bakın.
- **Self-host kopya seçili ama MathJax yine CDN'den yükleniyor:** eklentinin `mathjax/` dizini
  eksik (eksik yükleme ya da dağıtımda dışarıda bırakılmış dosyalar). Ayar formunda uyarı
  gösterilir ve sunucu hata günlüğüne kayıt düşülür. Dizini geri getirmek için eklenti paketini
  yeniden kurun ya da kaynağı CDN'e çevirin.

## Bilinen sınırlar

- **PDF galley kapsam dışıdır.** PDF içindeki matematik zaten PDF'e gömülüdür; MathJax'in
  yapacağı bir şey yoktur.
- **HTML galley koşulu:** tam metin render'ı için *HTML Article Galley* eklentisinin etkin
  olması gerekir — enjeksiyon yaptığımız galley dokümanını sunan eklenti odur. Kendi
  `</body>` etiketi olan tam dokümanlar da, çıplak parça olarak sunulan galley'ler de
  desteklenir.
- **Backend render'ı deneyseldir.** MathJax metin düğümlerini kendi elemanlarıyla değiştirirken
  aynı DOM'un sahibi Vue'dur; bir bileşen yeniden çizildiğinde formül kısa süre kaynak metin
  olarak görünür, ardından observer yeniden dizer. Düzenleme, arama ve kaydetme her zaman
  önceliklidir: backend render'ı bunları rahatsız ederse seçeneği kapatın — her iki durumda da
  editoryal veri etkilenmez. Düzenleme yüzeyleri açıkça dışarıda bırakılır: düz `input`
  değerleri metin düğümü değildir, `textarea` MathJax tarafından atlanır ve zengin metin
  editörleri (sayfa içinde `contenteditable` eleman olarak çalışırlar) işaretlenerek MathJax'in
  dışında tutulur — editör bir başlık veya özeti kaydettiğinde MathJax markup'ı değil, özgün
  LaTeX kaydedilir.
- **Kaynakçada tek ters bölü kaybolur.** OJS çekirdeği kayıtlı bir kaynağı okurken ters bölüleri
  siler (`Citation` sınıfı `stripslashes()` çağırır); bu yüzden `\(\gamma\)`, `(gamma)` olarak görünür.
  Kayıtlı metin değişmez. Çekirdek bu davranışı değiştirene kadar kaynakça alanında her ters
  bölüyü iki kez yazın: `Ozkan, T. (2021). Convergence of \\(\\gamma\\)-contraction operators.`
  formülü gösterir; aynı metin tek ters bölüyle yazılırsa `(gamma)` görünür. Başlık, özet
  ve galley dosyaları etkilenmez. Dergiler bu cümleyi yazar yönergelerine kopyalayabilir.
- **Kısaltılmış metin.** Özetleri kısaltan temalar (sayı ve arama sonucu kartları) formülü
  ortadan kesebilir; görünen parça düz TeX olur.
- **Düz metin yerleri.** Tarayıcı sekme başlığı, `citation_title` ve `DC.Title` meta etiketleri
  ile sayfanın yapılandırılmış verisi (JSON-LD) başlığı girildiği gibi, LaTeX dahil taşır.
  Eklenti bunlara dokunmaz.

## Üçüncü taraf bildirimleri

- MathJax © The MathJax Consortium — Apache License 2.0.
  `mathjax/` altındaki paket kopya, Apache 2.0 lisans dosyasıyla birlikte gelir
  (`mathjax/LICENSE`). Kaynak: resmî npm paketi `mathjax@3.2.2` (sağlaması npm
  kayıt defteriyle doğrulanmıştır).

## Destek

OJS Services tarafından geliştirilmiştir (https://ojs-services.com). Soru ve hata bildirimleri için bu depoda bir issue açın.

## Lisans

GPL v3. [LICENSE](LICENSE) dosyasına bakın. Pakete dahil MathJax kopyası Apache License 2.0 altındadır (`mathjax/LICENSE`).
