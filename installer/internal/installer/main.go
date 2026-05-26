package installer

import (
	"bufio"
	"bytes"
	"crypto/hmac"
	"crypto/sha256"
	"encoding/base64"
	"encoding/json"
	"flag"
	"fmt"
	"io"
	"net"
	"net/http"
	"os"
	"os/exec"
	"path/filepath"
	"runtime"
	"strings"
	"time"
)

type options struct {
	Profile          string
	Mode             string
	SubscriberCode   string
	ActivationURL    string
	ServiceToken     string
	InstallRoot      string
	SessionFile      string
	Port             string
	PrecheckOnly     bool
	OpenBrowser      bool
	NonInteractive   bool
	DevSigningKey    string
	ConfirmationCode string
}

type checkItem struct {
	Code     string `json:"code"`
	Label    string `json:"label"`
	Status   string `json:"status"`
	Message  string `json:"message"`
	Blocking bool   `json:"blocking"`
}

type activationSession struct {
	Profile                    string            `json:"profile"`
	SubscriberCode             string            `json:"subscriberCode"`
	Mode                       string            `json:"mode"`
	SessionID                  string            `json:"sessionId"`
	IssuedAt                   string            `json:"issuedAt"`
	ExpiresAt                  string            `json:"expiresAt"`
	ActivationProof            string            `json:"activationProof"`
	ManifestURL                string            `json:"manifestUrl,omitempty"`
	ComposeURL                 string            `json:"dockerComposeUrl,omitempty"`
	PackageURL                 string            `json:"packageUrl,omitempty"`
	ArtifactSignatureAlgorithm string            `json:"artifactSignatureAlgorithm,omitempty"`
	ArtifactSignatures         map[string]string `json:"artifactSignatures,omitempty"`
}

func Run(profile string) {
	opts := readOptions(profile)
	if err := execute(opts); err != nil {
		fmt.Fprintln(os.Stderr, "ERRO:", err)
		os.Exit(1)
	}
}

func readOptions(profile string) options {
	defaultMode := "native"
	if runtime.GOOS == "linux" {
		defaultMode = "docker"
	}
	opts := options{Profile: profile}
	flag.StringVar(&opts.Mode, "mode", defaultMode, "Modo: docker, native, saas-docker ou windows-test")
	flag.StringVar(&opts.SubscriberCode, "subscriber-code", "", "Codigo do assinante cadastrado na central")
	flag.StringVar(&opts.ActivationURL, "activation-url", env("CONSTRUTOR_ACTIVATION_URL", ""), "URL da central de ativacao")
	flag.StringVar(&opts.ServiceToken, "service-token", env("CONSTRUTOR_INSTALLER_SERVICE_TOKEN", ""), "Token interno para instalacao SaaS")
	flag.StringVar(&opts.InstallRoot, "install-root", ".", "Diretorio onde os artefatos serao preparados")
	flag.StringVar(&opts.SessionFile, "session-file", "", "Arquivo local de sessao que o backend validara")
	flag.StringVar(&opts.Port, "port", "8080", "Porta HTTP local esperada")
	flag.BoolVar(&opts.PrecheckOnly, "precheck", false, "Executa somente precheck")
	flag.BoolVar(&opts.OpenBrowser, "open-browser", true, "Abre o navegador ao final")
	flag.BoolVar(&opts.NonInteractive, "non-interactive", false, "Nao solicitar codigo por stdin")
	flag.StringVar(&opts.DevSigningKey, "dev-signing-key", env("CONSTRUTOR_INSTALLER_DEV_SIGNING_KEY", ""), "Chave local somente para gerar sessao em teste")
	flag.StringVar(&opts.ConfirmationCode, "confirmation-code", "", "Codigo recebido por e-mail")
	flag.Parse()
	return opts
}

func execute(opts options) error {
	opts.Mode = normalizeMode(opts.Mode)
	if opts.Profile == "system_builder" && opts.Mode == "windows-test" {
		return fmt.Errorf("o construtor nao deve ser instalado como producao no Windows")
	}
	if opts.SubscriberCode == "" {
		return fmt.Errorf("informe --subscriber-code")
	}

	checks := runPrecheck(opts)
	printChecks(checks)
	if hasBlocking(checks) {
		return fmt.Errorf("precheck encontrou bloqueios")
	}
	if opts.PrecheckOnly {
		return nil
	}

	session, err := activate(opts)
	if err != nil {
		return err
	}
	if err := writeSession(opts, session); err != nil {
		return err
	}
	if err := prepareArtifacts(opts, session); err != nil {
		return err
	}
	if opts.OpenBrowser {
		openBrowser("http://127.0.0.1:" + opts.Port + "/production/install.html")
	}
	fmt.Println("Instalador concluiu a preparacao. Continue pela pagina /production/install.html.")
	return nil
}

func runPrecheck(opts options) []checkItem {
	checks := []checkItem{
		checkOS(opts),
		checkArch(),
		checkPort(opts.Port),
		checkWritableDir(opts.installRootAbs()),
		checkInternet(opts.ActivationURL),
		checkClock(),
	}
	if runtime.GOOS == "linux" && opts.Mode == "docker" {
		checks = append(checks,
			checkCommand("docker", "Docker instalado", true),
			checkDockerCompose(),
			checkDockerPermission(),
		)
	}
	if opts.Mode == "native" || opts.Mode == "windows-test" {
		checks = append(checks,
			checkCommand("php", "PHP 8.4 no PATH", true),
			checkPHPVersion(),
			checkPHPExtensions([]string{"ctype", "iconv", "pdo_pgsql", "pgsql", "mbstring", "xml", "zip", "curl"}),
			checkCommand("composer", "Composer no PATH", true),
			checkCommand("psql", "psql no PATH", true),
			checkCommand("pg_dump", "pg_dump no PATH", true),
			checkCommand("pg_restore", "pg_restore no PATH", true),
		)
	}
	if runtime.GOOS == "windows" && opts.Mode == "docker" {
		checks = append(checks, checkItem{Code: "windows_docker_disabled", Label: "Docker no Windows", Status: "ERRO", Message: "Windows e apenas para teste sem Docker.", Blocking: true})
	}
	return checks
}

func activate(opts options) (activationSession, error) {
	if opts.ActivationURL == "" {
		if opts.DevSigningKey == "" {
			return activationSession{}, fmt.Errorf("configure --activation-url ou use --dev-signing-key apenas em teste")
		}
		return devSession(opts), nil
	}
	if opts.Mode == "saas-docker" {
		return activateSaaS(opts)
	}
	requestID, maskedEmail, err := requestActivation(opts)
	if err != nil {
		return activationSession{}, err
	}
	fmt.Println("Codigo enviado para:", maskedEmail)
	code := opts.ConfirmationCode
	if code == "" && !opts.NonInteractive {
		fmt.Print("Informe o codigo recebido por e-mail: ")
		line, _ := bufio.NewReader(os.Stdin).ReadString('\n')
		code = strings.TrimSpace(line)
	}
	if code == "" {
		return activationSession{}, fmt.Errorf("codigo de confirmacao nao informado")
	}
	return confirmActivation(opts, requestID, code)
}

func requestActivation(opts options) (string, string, error) {
	payload := map[string]string{
		"profile":        opts.Profile,
		"subscriberCode": opts.SubscriberCode,
		"mode":           opts.Mode,
		"fingerprint":    fingerprint(),
		"platform":       runtime.GOOS,
		"arch":           runtime.GOARCH,
	}
	var response struct {
		RequestID   string `json:"requestId"`
		MaskedEmail string `json:"maskedEmail"`
	}
	if err := postJSON(opts, "/api/installer/activation/request", payload, &response); err != nil {
		return "", "", err
	}
	if response.RequestID == "" {
		return "", "", fmt.Errorf("central nao retornou requestId")
	}
	return response.RequestID, response.MaskedEmail, nil
}

func confirmActivation(opts options, requestID string, code string) (activationSession, error) {
	payload := map[string]string{
		"requestId": requestID,
		"code":      code,
	}
	var response activationSession
	if err := postJSON(opts, "/api/installer/activation/confirm", payload, &response); err != nil {
		return activationSession{}, err
	}
	return normalizeSession(response, opts), nil
}

func activateSaaS(opts options) (activationSession, error) {
	payload := map[string]string{
		"profile":        opts.Profile,
		"subscriberCode": opts.SubscriberCode,
		"mode":           opts.Mode,
		"fingerprint":    fingerprint(),
		"platform":       runtime.GOOS,
		"arch":           runtime.GOARCH,
	}
	var response activationSession
	if err := postJSON(opts, "/api/installer/activation/service", payload, &response); err != nil {
		return activationSession{}, err
	}
	return normalizeSession(response, opts), nil
}

func postJSON(opts options, path string, payload any, target any) error {
	body, _ := json.Marshal(payload)
	request, err := http.NewRequest("POST", strings.TrimRight(opts.ActivationURL, "/")+path, bytes.NewReader(body))
	if err != nil {
		return err
	}
	request.Header.Set("Content-Type", "application/json")
	request.Header.Set("Accept", "application/json")
	if opts.ServiceToken != "" {
		request.Header.Set("Authorization", "Bearer "+opts.ServiceToken)
	}
	client := &http.Client{Timeout: 30 * time.Second}
	response, err := client.Do(request)
	if err != nil {
		return err
	}
	defer response.Body.Close()
	responseBody, _ := io.ReadAll(response.Body)
	if response.StatusCode < 200 || response.StatusCode > 299 {
		return fmt.Errorf("central retornou HTTP %d: %s", response.StatusCode, string(responseBody))
	}
	return json.Unmarshal(responseBody, target)
}

func writeSession(opts options, session activationSession) error {
	path := opts.sessionFileAbs()
	if err := os.MkdirAll(filepath.Dir(path), 0o700); err != nil {
		return err
	}
	payload, _ := json.MarshalIndent(session, "", "  ")
	return os.WriteFile(path, payload, 0o600)
}

func prepareArtifacts(opts options, session activationSession) error {
	if session.ManifestURL != "" {
		if err := verifyArtifactSignature(session, "manifestUrl", session.ManifestURL); err != nil {
			return err
		}
		if err := downloadFile(session.ManifestURL, filepath.Join(opts.installRootAbs(), "installer-manifest.json")); err != nil {
			return err
		}
	}
	if session.ComposeURL != "" {
		if err := verifyArtifactSignature(session, "dockerComposeUrl", session.ComposeURL); err != nil {
			return err
		}
		if err := downloadFile(session.ComposeURL, filepath.Join(opts.installRootAbs(), "compose.installer.yaml")); err != nil {
			return err
		}
	}
	if session.PackageURL != "" {
		if err := verifyArtifactSignature(session, "packageUrl", session.PackageURL); err != nil {
			return err
		}
		if err := downloadFile(session.PackageURL, filepath.Join(opts.installRootAbs(), "construtor-pg-package.zip")); err != nil {
			return err
		}
	}
	if runtime.GOOS == "linux" && opts.Mode == "docker" {
		composeFile := "compose.yaml"
		if session.ComposeURL != "" {
			composeFile = "compose.installer.yaml"
		}
		command := exec.Command("docker", "compose", "-f", composeFile, "up", "-d")
		command.Dir = opts.installRootAbs()
		command.Stdout = os.Stdout
		command.Stderr = os.Stderr
		return command.Run()
	}
	return nil
}

func verifyArtifactSignature(session activationSession, name string, url string) error {
	if session.ArtifactSignatureAlgorithm == "" || session.ArtifactSignatureAlgorithm == "none" {
		return nil
	}
	if session.ArtifactSignatureAlgorithm != "hmac-sha256" {
		return fmt.Errorf("algoritmo de assinatura de artefato nao suportado: %s", session.ArtifactSignatureAlgorithm)
	}
	key := env("CONSTRUTOR_INSTALLER_ARTIFACT_SIGNING_KEY", "")
	if key == "" {
		return fmt.Errorf("assinatura de artefato recebida, mas CONSTRUTOR_INSTALLER_ARTIFACT_SIGNING_KEY nao foi configurada")
	}
	expected := session.ArtifactSignatures[name]
	if expected == "" {
		return fmt.Errorf("assinatura ausente para artefato %s", name)
	}
	payload := struct {
		Name           string `json:"name"`
		URL            string `json:"url"`
		Profile        string `json:"profile"`
		SubscriberCode string `json:"subscriberCode"`
		Mode           string `json:"mode"`
		SessionID      string `json:"sessionId"`
		ExpiresAt      string `json:"expiresAt"`
	}{
		Name:           name,
		URL:            url,
		Profile:        session.Profile,
		SubscriberCode: session.SubscriberCode,
		Mode:           session.Mode,
		SessionID:      session.SessionID,
		ExpiresAt:      session.ExpiresAt,
	}
	encoded, _ := json.Marshal(payload)
	mac := hmac.New(sha256.New, []byte(key))
	mac.Write(encoded)
	actual := fmt.Sprintf("%x", mac.Sum(nil))
	if !hmac.Equal([]byte(actual), []byte(expected)) {
		return fmt.Errorf("assinatura invalida para artefato %s", name)
	}
	return nil
}

func downloadFile(url string, path string) error {
	response, err := http.Get(url)
	if err != nil {
		return err
	}
	defer response.Body.Close()
	if response.StatusCode < 200 || response.StatusCode > 299 {
		return fmt.Errorf("download %s retornou HTTP %d", url, response.StatusCode)
	}
	file, err := os.Create(path)
	if err != nil {
		return err
	}
	defer file.Close()
	_, err = io.Copy(file, response.Body)
	return err
}

func devSession(opts options) activationSession {
	now := time.Now().UTC()
	payload := map[string]string{
		"profile":        opts.Profile,
		"subscriberCode": opts.SubscriberCode,
		"mode":           opts.Mode,
		"sessionId":      "dev-" + now.Format("20060102150405"),
		"issuedAt":       now.Format(time.RFC3339),
		"expiresAt":      now.Add(2 * time.Hour).Format(time.RFC3339),
	}
	proof := signPayload(payload, opts.DevSigningKey)
	return activationSession{
		Profile:         payload["profile"],
		SubscriberCode:  payload["subscriberCode"],
		Mode:            payload["mode"],
		SessionID:       payload["sessionId"],
		IssuedAt:        payload["issuedAt"],
		ExpiresAt:       payload["expiresAt"],
		ActivationProof: proof,
	}
}

func signPayload(payload map[string]string, key string) string {
	encoded, _ := json.Marshal(payload)
	payloadPart := base64.RawURLEncoding.EncodeToString(encoded)
	mac := hmac.New(sha256.New, []byte(key))
	mac.Write([]byte(payloadPart))
	return payloadPart + "." + base64.RawURLEncoding.EncodeToString(mac.Sum(nil))
}

func normalizeSession(session activationSession, opts options) activationSession {
	if session.Profile == "" {
		session.Profile = opts.Profile
	}
	if session.SubscriberCode == "" {
		session.SubscriberCode = opts.SubscriberCode
	}
	if session.Mode == "" {
		session.Mode = opts.Mode
	}
	return session
}

func checkOS(opts options) checkItem {
	if runtime.GOOS == "linux" {
		data, err := os.ReadFile("/etc/os-release")
		if err != nil {
			return checkItem{"os", "Sistema operacional", "ERRO", "Nao foi possivel ler /etc/os-release.", true}
		}
		text := string(data)
		if !strings.Contains(text, `ID=ubuntu`) || !strings.Contains(text, `VERSION_ID="24.04"`) {
			return checkItem{"os", "Sistema operacional", "ERRO", "Producao exige Ubuntu 24.04.", true}
		}
		return checkItem{"os", "Sistema operacional", "OK", "Ubuntu 24.04.", false}
	}
	if runtime.GOOS == "windows" {
		if opts.Mode != "windows-test" && opts.Mode != "native" {
			return checkItem{"os", "Sistema operacional", "ERRO", "Windows e permitido apenas para teste sem Docker.", true}
		}
		return checkItem{"os", "Sistema operacional", "AVISO", "Windows permitido somente para teste.", false}
	}
	return checkItem{"os", "Sistema operacional", "ERRO", "Sistema nao suportado: " + runtime.GOOS, true}
}

func checkArch() checkItem {
	if runtime.GOARCH == "amd64" || runtime.GOARCH == "arm64" {
		return checkItem{"arch", "Arquitetura", "OK", runtime.GOARCH, false}
	}
	return checkItem{"arch", "Arquitetura", "ERRO", "Arquitetura nao suportada: " + runtime.GOARCH, true}
}

func checkCommand(name, label string, blocking bool) checkItem {
	if _, err := exec.LookPath(name); err != nil {
		status := "AVISO"
		if blocking {
			status = "ERRO"
		}
		return checkItem{name, label, status, name + " nao encontrado no PATH.", blocking}
	}
	return checkItem{name, label, "OK", name + " encontrado.", false}
}

func checkDockerCompose() checkItem {
	command := exec.Command("docker", "compose", "version")
	if err := command.Run(); err != nil {
		return checkItem{"docker_compose", "Docker Compose plugin", "ERRO", "docker compose version falhou.", true}
	}
	return checkItem{"docker_compose", "Docker Compose plugin", "OK", "Plugin disponivel.", false}
}

func checkDockerPermission() checkItem {
	command := exec.Command("docker", "ps")
	if err := command.Run(); err != nil {
		return checkItem{"docker_permission", "Permissao Docker", "ERRO", "Usuario sem permissao para executar docker ps.", true}
	}
	return checkItem{"docker_permission", "Permissao Docker", "OK", "Permissao confirmada.", false}
}

func checkPHPVersion() checkItem {
	output, err := exec.Command("php", "-r", "echo PHP_VERSION;").Output()
	if err != nil {
		return checkItem{"php_version", "Versao PHP", "ERRO", "Nao foi possivel executar PHP.", true}
	}
	version := string(output)
	if !strings.HasPrefix(version, "8.4.") {
		return checkItem{"php_version", "Versao PHP", "ERRO", "PHP 8.4 exigido; encontrado " + version, true}
	}
	return checkItem{"php_version", "Versao PHP", "OK", version, false}
}

func checkPHPExtensions(names []string) checkItem {
	code := "echo implode(',', get_loaded_extensions());"
	output, err := exec.Command("php", "-r", code).Output()
	if err != nil {
		return checkItem{"php_extensions", "Extensoes PHP", "ERRO", "Nao foi possivel listar extensoes PHP.", true}
	}
	loaded := "," + strings.ToLower(string(output)) + ","
	missing := []string{}
	for _, name := range names {
		if !strings.Contains(loaded, ","+strings.ToLower(name)+",") {
			missing = append(missing, name)
		}
	}
	if len(missing) > 0 {
		return checkItem{"php_extensions", "Extensoes PHP", "ERRO", "Extensoes ausentes: " + strings.Join(missing, ", "), true}
	}
	return checkItem{"php_extensions", "Extensoes PHP", "OK", "Extensoes obrigatorias carregadas.", false}
}

func checkPort(port string) checkItem {
	listener, err := net.Listen("tcp", "127.0.0.1:"+port)
	if err != nil {
		return checkItem{"port", "Porta HTTP", "ERRO", "Porta " + port + " ocupada.", true}
	}
	_ = listener.Close()
	return checkItem{"port", "Porta HTTP", "OK", "Porta " + port + " livre.", false}
}

func checkWritableDir(dir string) checkItem {
	if err := os.MkdirAll(dir, 0o755); err != nil {
		return checkItem{"write_dir", "Diretorio de instalacao", "ERRO", err.Error(), true}
	}
	test := filepath.Join(dir, ".installer-write-test")
	if err := os.WriteFile(test, []byte("ok"), 0o600); err != nil {
		return checkItem{"write_dir", "Diretorio de instalacao", "ERRO", "Sem permissao de escrita.", true}
	}
	_ = os.Remove(test)
	return checkItem{"write_dir", "Diretorio de instalacao", "OK", dir, false}
}

func checkInternet(activationURL string) checkItem {
	if activationURL == "" {
		return checkItem{"internet", "Central de ativacao", "AVISO", "URL da central nao informada; somente teste local com chave dev funcionara.", false}
	}
	client := &http.Client{Timeout: 10 * time.Second}
	response, err := client.Get(strings.TrimRight(activationURL, "/") + "/health")
	if err != nil {
		return checkItem{"internet", "Central de ativacao", "ERRO", err.Error(), true}
	}
	_ = response.Body.Close()
	if response.StatusCode >= 500 {
		return checkItem{"internet", "Central de ativacao", "ERRO", fmt.Sprintf("Central retornou HTTP %d.", response.StatusCode), true}
	}
	return checkItem{"internet", "Central de ativacao", "OK", "Central acessivel.", false}
}

func checkClock() checkItem {
	now := time.Now()
	if now.Year() < 2026 {
		return checkItem{"clock", "Relogio do servidor", "ERRO", "Data/hora parece incorreta.", true}
	}
	return checkItem{"clock", "Relogio do servidor", "OK", now.Format(time.RFC3339), false}
}

func printChecks(checks []checkItem) {
	for _, item := range checks {
		fmt.Printf("%-5s  %-28s %s\n", item.Status, item.Label, item.Message)
	}
}

func hasBlocking(checks []checkItem) bool {
	for _, item := range checks {
		if item.Blocking && item.Status == "ERRO" {
			return true
		}
	}
	return false
}

func fingerprint() string {
	host, _ := os.Hostname()
	sum := sha256.Sum256([]byte(host + "|" + runtime.GOOS + "|" + runtime.GOARCH))
	return base64.RawURLEncoding.EncodeToString(sum[:])
}

func normalizeMode(value string) string {
	value = strings.TrimSpace(strings.ToLower(value))
	if runtime.GOOS == "windows" && (value == "" || value == "native") {
		return "windows-test"
	}
	if value == "" {
		return "docker"
	}
	return value
}

func (opts options) installRootAbs() string {
	path, err := filepath.Abs(opts.InstallRoot)
	if err != nil {
		return opts.InstallRoot
	}
	return path
}

func (opts options) sessionFileAbs() string {
	if opts.SessionFile != "" {
		path, err := filepath.Abs(opts.SessionFile)
		if err == nil {
			return path
		}
		return opts.SessionFile
	}
	return filepath.Join(opts.installRootAbs(), "backend", "var", "install", "activation-session.json")
}

func env(name, fallback string) string {
	value := strings.TrimSpace(os.Getenv(name))
	if value == "" {
		return fallback
	}
	return value
}

func openBrowser(url string) {
	var command *exec.Cmd
	switch runtime.GOOS {
	case "windows":
		command = exec.Command("rundll32", "url.dll,FileProtocolHandler", url)
	case "linux":
		command = exec.Command("xdg-open", url)
	default:
		fmt.Println("Abra no navegador:", url)
		return
	}
	if err := command.Start(); err != nil {
		fmt.Println("Abra no navegador:", url)
	}
}
