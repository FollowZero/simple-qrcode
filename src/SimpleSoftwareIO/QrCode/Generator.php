<?php

namespace SimpleSoftwareIO\QrCode;

use BaconQrCode\Common\ErrorCorrectionLevel;
use BaconQrCode\Encoder\Encoder;
use BaconQrCode\Exception\WriterException;
use BaconQrCode\Renderer\Color\Alpha;
use BaconQrCode\Renderer\Color\ColorInterface;
use BaconQrCode\Renderer\Color\Gray;
use BaconQrCode\Renderer\Color\Rgb;
use BaconQrCode\Renderer\Eye\EyeInterface;
use BaconQrCode\Renderer\Eye\ModuleEye;
use BaconQrCode\Renderer\Eye\SimpleCircleEye;
use BaconQrCode\Renderer\Eye\SquareEye;
use BaconQrCode\Renderer\Image\EpsImageBackEnd;
use BaconQrCode\Renderer\Image\ImageBackEndInterface;
use BaconQrCode\Renderer\Image\ImagickImageBackEnd;
use BaconQrCode\Renderer\Image\SvgImageBackEnd;
use BaconQrCode\Renderer\ImageRenderer;
use BaconQrCode\Renderer\Module\DotsModule;
use BaconQrCode\Renderer\Module\ModuleInterface;
use BaconQrCode\Renderer\Module\RoundnessModule;
use BaconQrCode\Renderer\Module\SquareModule;
use BaconQrCode\Renderer\RendererStyle\EyeFill;
use BaconQrCode\Renderer\RendererStyle\Fill;
use BaconQrCode\Renderer\RendererStyle\Gradient;
use BaconQrCode\Renderer\RendererStyle\GradientType;
use BaconQrCode\Renderer\RendererStyle\RendererStyle;
use BaconQrCode\Writer;
use BadMethodCallException;
use InvalidArgumentException;
use SimpleSoftwareIO\QrCode\DataTypes\DataTypeInterface;

class Generator
{
    /**
     * Holds the selected formatter.
     *
     * @var string
     */
    protected $format = 'svg';

    /**
     * Holds the size of the QrCode in pixels.
     *
     * @var int
     */
    protected $pixels = 100;

    /**
     * Holds the margin size of the QrCode.
     *
     * @var int
     */
    protected $margin = 0;

    /**
     * Holds the selected error correction.
     * L: 7% loss.
     * M: 15% loss.
     * Q: 25% loss.
     * H: 30% loss.
     *
     * @var string|null
     */
    protected $errorCorrection = null;

    /**
     * Holds the selected encoder.  Possible values are
     * ISO-8859-2, ISO-8859-3, ISO-8859-4, ISO-8859-5, ISO-8859-6,
     * ISO-8859-7, ISO-8859-8, ISO-8859-9, ISO-8859-10, ISO-8859-11,
     * ISO-8859-12, ISO-8859-13, ISO-8859-14, ISO-8859-15, ISO-8859-16,
     * SHIFT-JIS, WINDOWS-1250, WINDOWS-1251, WINDOWS-1252, WINDOWS-1256,
     * UTF-16BE, UTF-8, ASCII, GBK, EUC-KR.
     *
     * @var string
     */
    protected $encoding = Encoder::DEFAULT_BYTE_MODE_ECODING;

    /**
     * The style of the blocks within the QrCode.
     * Possible values are square, dot, and round.
     *
     * @var string
     */
    protected $style = 'square';

    /**
     * The size of the selected style between 0 and 1.
     * This only applies to dot and round.
     *
     * @var float|null
     */
    protected $styleSize = null;

    /**
     * The style to apply to the eye.
     * Possible values are circle and square.
     *
     * @var string|null
     */
    protected $eyeStyle = null;

    /**
     * The foreground color of the QrCode.
     *
     * @var ColorInterface|null
     */
    protected $color = null;

    /**
     * The background color of the QrCode.
     *
     * @var ColorInterface|null
     */
    protected $backgroundColor = null;

    /**
     * An array that holds EyeFills of the color of the eyes.
     *
     * @var array
     */
    protected $eyeColors = [];

    /**
     * The gradient to apply to the QrCode.
     *
     * @var Gradient
     */
    protected $gradient;

    /**
     * Holds an image string that will be merged with the QrCode.
     *
     * @var null|string
     */
    protected $imageMerge = null;

    /**
     * The percentage that a merged image should take over the source image.
     *
     * @var float
     */
    protected $imagePercentage = .2;

    /**
     * Creates a new datatype object and then generates a QrCode.
     *
     * @param $method
     * @param array $arguments
     */
    public function __call($method, array $arguments)
    {
        $dataType = $this->createClass($method);
        $dataType->create($arguments);

        return $this->generate(strval($dataType));
    }

    /**
     * Generates the QrCode.
     *
     * @param string $text
     * @param string|null $filename
     * @return void|string
     * @throws WriterException
     * @throws InvalidArgumentException
     */
    public function generate(string $text, string $filename = null)
    {
        $qrCode = $this->getWriter($this->getRenderer())->writeString($text, $this->encoding, $this->errorCorrection);

        if ($this->imageMerge !== null && $this->format === 'png') {
            $merger = new ImageMerge(new Image($qrCode), new Image($this->imageMerge));
            $qrCode = $merger->merge($this->imagePercentage);
        }

        if ($filename) {
            file_put_contents($filename, $qrCode);

            return;
        }

        return $qrCode;
    }

    /**
     * Merges an image over the QrCode.
     *
     * @param string $filepath
     * @param float $percentage
     * @param SimpleSoftwareIO\QrCode\boolean|bool $absolute
     * @return Generator
     */
    public function merge(string $filepath, float $percentage = .2, bool $absolute = false): self
    {
        if (function_exists('base_path') && ! $absolute) {
            $filepath = base_path().$filepath;
        }

        $this->imageMerge = file_get_contents($filepath);
        $this->imagePercentage = $percentage;

        return $this;
    }

    /**
     * Merges an image string with the center of the QrCode.
     *
     * @param string  $content
     * @param float $percentage
     * @return Generator
     */
    public function mergeString(string $content, float $percentage = .2): self
    {
        $this->imageMerge = $content;
        $this->imagePercentage = $percentage;

        return $this;
    }

    /**
     * Sets the size of the QrCode.
     *
     * @param int $pixels
     * @return Generator
     */
    public function size(int $pixels): self
    {
        $this->pixels = $pixels;

        return $this;
    }

    /**
     * Sets the format of the QrCode.
     *
     * @param string $format
     * @return Generator
     * @throws InvalidArgumentException
     */
    public function format(string $format): self
    {
        if (! in_array($format, ['svg', 'eps', 'png'])) {
            throw new InvalidArgumentException("\$format must be svg, eps, or png. {$format} is not a valid.");
        }

        $this->format = $format;

        return $this;
    }

    /**
     * Sets the foreground color of the QrCode.
     *
     * @param int $red
     * @param int $green
     * @param int $blue
     * @param null|int $alpha
     * @return Generator
     */
    public function color(int $red, int $green, int $blue, ?int $alpha = null): self
    {
        $this->color = $this->createColor($red, $green, $blue, $alpha);

        return $this;
    }

    /**
     * Sets the background color of the QrCode.
     *
     * @param int $red
     * @param int $green
     * @param int $blue
     * @param null|int $alpha
     * @return Generator
     */
    public function backgroundColor(int $red, int $green, int $blue, ?int $alpha = null): self
    {
        $this->backgroundColor = $this->createColor($red, $green, $blue, $alpha);

        return $this;
    }

    /**
     * Sets the eye color for the selected eye.
     *
     * @param int $eyeNumber
     * @param array $innerColor
     * @param array|null $outterColor
     * @return Generator
     * @throws InvalidArgumentException
     */
    public function eyeColor(int $eyeNumber, array $innerColor, array $outterColor = null): self
    {
        if ($eyeNumber < 0 || $eyeNumber > 2) {
            throw new InvalidArgumentException("\$eyeNumber must be 0, 1, or 2.  {$eyeNumber} is not valid.");
        }

        $this->eyeColors[$eyeNumber] = new EyeFill(new Rgb(...$innerColor), new Rgb(...$outterColor));

        return $this;
    }

    /**
     * Sets the gradient for the QrCode.
     *
     * @param array $startColor
     * @param array $endColor
     * @param string $type
     * @return Generator
     */
    public function gradient(array $startColor, array $endColor, string $type): self
    {
        $type = strtoupper($type);
        $this->gradient = new Gradient($this->createColor(...$startColor), $this->createColor(...$endColor), GradientType::$type());

        return $this;
    }

    /**
     * Sets the eye style.
     *
     * @param string $style
     * @return Generator
     * @throws InvalidArgumentException
     */
    public function eye(string $style): self
    {
        if (! in_array($style, ['sqaure', 'circle'])) {
            throw new InvalidArgumentException("\$style must be square or circle. {$style} is not a valid eye style.");
        }

        $this->eyeStyle = $style;

        return $this;
    }

    /**
     * Sets the style of the blocks for the QrCode.
     *
     * @param string $style
     * @param float $size
     * @return Generator
     * @throws InvalidArgumentException
     */
    public function style(string $style, float $size = 0.5): self
    {
        if (! in_array($style, ['square', 'dot', 'round'])) {
            throw new InvalidArgumentException("\$style must be square, dot, or round. {$style} is not a valid.");
        }

        if ($size < 0 || $size >= 1) {
            throw new InvalidArgumentException("\$size must be between 0 and 1.  {$size} is not valid.");
        }

        $this->style = $style;
        $this->styleSize = $size;

        return $this;
    }

    /**
     * Sets the encoding for the QrCode.
     * Possible values are
     * ISO-8859-2, ISO-8859-3, ISO-8859-4, ISO-8859-5, ISO-8859-6,
     * ISO-8859-7, ISO-8859-8, ISO-8859-9, ISO-8859-10, ISO-8859-11,
     * ISO-8859-12, ISO-8859-13, ISO-8859-14, ISO-8859-15, ISO-8859-16,
     * SHIFT-JIS, WINDOWS-1250, WINDOWS-1251, WINDOWS-1252, WINDOWS-1256,
     * UTF-16BE, UTF-8, ASCII, GBK, EUC-KR.
     *
     * @param string $encoding
     * @return Generator
     */
    public function encoding(string $encoding): self
    {
        $this->encoding = strtoupper($encoding);

        return $this;
    }

    /**
     * Sets the error correction for the QrCode.
     * L: 7% loss.
     * M: 15% loss.
     * Q: 25% loss.
     * H: 30% loss.
     *
     * @param string $errorCorrection
     * @return Generator
     */
    public function errorCorrection(string $errorCorrection): self
    {
        $errorCorrection = strtoupper($errorCorrection);
        $this->errorCorrection = ErrorCorrectionLevel::$errorCorrection();

        return $this;
    }

    /**
     * Sets the margin of the QrCode.
     *
     * @param int $margin
     * @return Generator
     */
    public function margin(int $margin): self
    {
        $this->margin = $margin;

        return $this;
    }

    /**
     * Fetches the Writer.
     *
     * @param ImageRenderer $renderer
     * @return Writer
     */
    protected function getWriter(ImageRenderer $renderer): Writer
    {
        return new Writer($renderer);
    }

    /**
     * Fetches the Image Renderer.
     *
     * @return ImageRenderer
     */
    protected function getRenderer(): ImageRenderer
    {
        $renderer = new ImageRenderer(
            new RendererStyle($this->pixels, $this->margin, $this->getModule(), $this->getEye(), $this->getFill()),
            $this->getFormatter()
        );

        return $renderer;
    }

    /**
     * Fetches the formatter.
     *
     * @return ImageBackEndInterface
     */
    protected function getFormatter(): ImageBackEndInterface
    {
        if ($this->format === 'png') {
            return new ImagickImageBackEnd('png');
        }

        if ($this->format === 'eps') {
            return new EpsImageBackEnd;
        }

        return new SvgImageBackEnd;
    }

    /**
     * Fetches the module.
     *
     * @return ModuleInterface
     */
    protected function getModule(): ModuleInterface
    {
        if ($this->style === 'dot') {
            return new DotsModule($this->styleSize);
        }

        if ($this->style === 'round') {
            return new RoundnessModule($this->styleSize);
        }

        return SquareModule::instance();
    }

    /**
     * Fetches the eye style.
     *
     * @return EyeInterface
     */
    protected function getEye(): EyeInterface
    {
        if ($this->eyeStyle === 'square') {
            return SquareEye::instance();
        }

        if ($this->eyeStyle === 'circle') {
            return SimpleCircleEye::instance();
        }

        return new ModuleEye($this->getModule());
    }

    /**
     * Fetches the color fill.
     *
     * @return Fill
     */
    protected function getFill(): Fill
    {
        $foregroundColor = $this->color ?? new Gray(100);
        $backgroundColor = $this->backgroundColor ?? new Gray(0);
        $eye1 = $this->eyeColors[0] ?? EyeFill::uniform(new Gray(0));
        $eye2 = $this->eyeColors[1] ?? EyeFill::uniform(new Gray(0));
        $eye3 = $this->eyeColors[2] ?? EyeFill::uniform(new Gray(0));

        if ($this->gradient) {
            return Fill::withForegroundGradient($foregroundColor, $this->gradient, $eye1, $eye2, $eye3);
        }

        return Fill::withForegroundColor($foregroundColor, $backgroundColor, $eye1, $eye2, $eye3);
    }

    /**
     * Creates a RGB or Alpha channel color.
     * @param int $red
     * @param int $green
     * @param int $blue
     * @param null|int $alpha
     * @return ColorInterface
     */
    protected function createColor(int $red, int $green, int $blue, ?int $alpha = null): ColorInterface
    {
        if (! $alpha) {
            return new Rgb($red, $green, $blue);
        }

        return new Alpha($alpha, new Rgb($red, $green, $blue));
    }

    /**
     * Creates a new DataType class dynamically.
     *
     * @param string $method
     * @return DataTypeInterface
     */
    protected function createClass(string $method): DataTypeInterface
    {
        $class = $this->formatClass($method);

        if (! class_exists($class)) {
            throw new BadMethodCallException();
        }

        return new $class();
    }

    /**
     * Formats the method name correctly.
     *
     * @param $method
     * @return string
     */
    protected function formatClass(string $method): string
    {
        $method = ucfirst($method);

        $class = "SimpleSoftwareIO\QrCode\DataTypes\\".$method;

        return $class;
    }
}
