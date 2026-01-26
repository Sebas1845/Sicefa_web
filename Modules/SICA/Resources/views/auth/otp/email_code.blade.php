<!doctype html>
<html lang="es">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Código de seguridad</title>
</head>
<body style="margin:0;padding:0;background:#f5f7fb;font-family:Arial,Helvetica,sans-serif;color:#111827;">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="background:#f5f7fb;padding:24px 12px;">
    <tr>
      <td align="center">
        <table role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:600px;max-width:600px;background:#ffffff;border-radius:12px;overflow:hidden;border:1px solid #e5e7eb;">
          <tr>
            <td style="padding:18px 20px;background:#0f172a;color:#ffffff;">
              <div style="font-size:16px;font-weight:700;">SICEFA</div>
              <div style="font-size:12px;opacity:.9;margin-top:2px;">Código de seguridad</div>
            </td>
          </tr>

          <tr>
            <td style="padding:22px 20px;">
              <p style="margin:0 0 12px 0;font-size:14px;line-height:1.5;">
                Has solicitado un código para iniciar sesión. Tu código de seguridad es:
              </p>

              <div style="margin:16px 0 18px 0;padding:14px 12px;border:1px dashed #cbd5e1;border-radius:10px;text-align:center;background:#f8fafc;">
                <div style="font-size:28px;font-weight:800;letter-spacing:6px;color:#0f172a;">
                  {{ $code }}
                </div>
              </div>

              <p style="margin:0 0 12px 0;font-size:14px;line-height:1.5;">
                Este código vence en <strong>{{ $ttlMinutes }} minutos</strong>.
              </p>

              <p style="margin:0;font-size:13px;line-height:1.5;color:#374151;">
                Si tú no solicitaste este código, ignora este correo. Por tu seguridad, no compartas este código con nadie.
              </p>
            </td>
          </tr>

          <tr>
            <td style="padding:14px 20px;background:#f8fafc;border-top:1px solid #e5e7eb;">
              <p style="margin:0;font-size:12px;color:#6b7280;line-height:1.4;">
                Este mensaje fue generado automáticamente. No respondas a este correo.
              </p>
            </td>
          </tr>
        </table>

        <div style="max-width:600px;margin-top:10px;font-size:11px;color:#9ca3af;line-height:1.4;text-align:center;">
          © {{ date('Y') }} SICEFA
        </div>
      </td>
    </tr>
  </table>
</body>
</html>
